<?php

namespace App\Services\Admin;

use App\Enums\UserVerificationStatus;
use App\Models\AdminUserNote;
use App\Models\KycAuditEvent;
use App\Models\KycDecision;
use App\Models\KycDocument;
use App\Models\KycReviewCase;
use App\Models\KycSetting;
use App\Models\User;
use App\Notifications\KycDecisionNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycCentreService
{
    public function summary(): array
    {
        $pending = KycReviewCase::query()->whereIn('status', ['pending', 'in_review']);
        $total = (clone $pending)->count();
        $avgSeconds = (clone $pending)->get()->avg(fn (KycReviewCase $case) => $case->entered_queue_at?->diffInSeconds(now()) ?? 0);

        return [
            'total' => $total,
            'critical' => (clone $pending)->where('priority', 'critical')->count(),
            'high' => (clone $pending)->where('priority', 'high')->count(),
            'medium' => (clone $pending)->where('priority', 'medium')->count(),
            'standard' => (clone $pending)->where('priority', 'standard')->count(),
            'average_wait_seconds' => (int) round((float) $avgSeconds),
            'average_wait_label' => $this->duration((int) round((float) $avgSeconds)),
        ];
    }

    public function queue(Request $request): LengthAwarePaginator
    {
        $query = KycReviewCase::query()
            ->with(['user.role:id,slug,name', 'verification', 'assignedAdmin:id,name,email'])
            ->whereIn('status', ['pending', 'in_review']);

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('role')) {
            $query->whereHas('user.role', fn (Builder $q) => $q->where('slug', $request->input('role')));
        }
        if ($request->filled('tier')) {
            $query->where('target_tier', (int) $request->input('tier'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }

        $sort = (string) $request->input('sort', 'priority');
        if ($sort === 'wait_time') {
            $query->oldest('entered_queue_at');
        } elseif ($sort === 'role') {
            $query->join('users', 'users.id', '=', 'kyc_review_cases.user_id')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->orderBy('roles.slug')
                ->select('kyc_review_cases.*');
        } else {
            $query->orderByRaw("field(priority, 'critical', 'high', 'medium', 'standard')")
                ->oldest('entered_queue_at');
        }

        return $query->paginate(min(50, max(10, $request->integer('per_page', 20))))
            ->withQueryString()
            ->through(fn (KycReviewCase $case) => $this->queueRow($case));
    }

    public function casePayload(KycReviewCase $case, ?User $admin = null, bool $includeDocuments = false): array
    {
        $case->loadMissing(['user.role', 'documents', 'verification', 'decisions.admin']);

        if ($includeDocuments && $admin) {
            $this->audit($case, $admin, 'documents_opened');
        }

        return [
            ...$this->queueRow($case),
            'submitted' => $this->maskSensitiveSnapshot($case->submitted_snapshot ?? []),
            'provider' => $this->maskSensitiveSnapshot($case->provider_snapshot ?? []),
            'comparison' => $case->comparison ?? [],
            'documents' => $includeDocuments ? $case->documents->map(fn (KycDocument $doc) => [
                'id' => $doc->id,
                'label' => $doc->label,
                'document_type' => $doc->document_type,
                'mime_type' => $doc->mime_type,
                'url' => $doc->temporaryUrl(),
            ])->values()->all() : [],
            'decisions' => $case->decisions->map(fn (KycDecision $decision) => [
                'action' => $decision->action,
                'reason' => $decision->reason_code,
                'note' => $decision->note,
                'admin' => $decision->admin?->name,
                'created_at' => $decision->created_at?->toIso8601String(),
            ]),
            'duplicate_context' => $case->queue_reason === 'duplicate_identity' ? $this->duplicateContext($case) : null,
        ];
    }

    public function reveal(KycReviewCase $case, User $admin, string $field): array
    {
        $allowed = ['identifier_number', 'nin', 'bvn', 'phone'];
        abort_unless(in_array($field, $allowed, true), 404);
        $this->audit($case, $admin, 'sensitive_field_revealed', ['field' => $field]);

        return [
            'field' => $field,
            'value' => data_get($case->submitted_snapshot, $field) ?? data_get($case->provider_snapshot, $field),
        ];
    }

    public function streamDocument(KycDocument $document, User $admin): StreamedResponse
    {
        $case = $document->case;
        $this->audit($case, $admin, 'document_opened', ['document_id' => $document->id, 'label' => $document->label]);

        return Storage::disk($document->disk ?: 'local')->download($document->path, $document->original_name ?: basename($document->path));
    }

    public function decide(KycReviewCase $case, User $admin, array $data): KycDecision
    {
        return DB::transaction(function () use ($case, $admin, $data): KycDecision {
            $case->loadMissing(['user', 'verification']);
            $action = $data['action'];
            $seconds = max(0, $case->entered_queue_at?->diffInSeconds(now()) ?? 0);

            $decision = $case->decisions()->create([
                'admin_user_id' => $admin->id,
                'action' => $action,
                'reason_code' => $data['reason_code'],
                'note' => $data['note'] ?? null,
                'correction_fields' => $data['correction_fields'] ?? null,
                'portfolio_scores' => $data['portfolio_scores'] ?? null,
                'time_to_decision_seconds' => $seconds,
            ]);

            $this->applyDecision($case, $action, $data);

            $case->forceFill([
                'status' => in_array($action, ['request_correction', 'reject_investigate'], true) ? $action : 'decided',
                'decision' => $action,
                'decision_reason' => $data['reason_code'],
                'decision_note' => $data['note'] ?? null,
                'assigned_admin_id' => $admin->id,
                'decided_at' => now(),
            ])->save();

            $this->audit($case, $admin, 'decision_'.$action, ['decision_id' => $decision->id, 'reason' => $data['reason_code']]);
            $this->notifyUser($case, $action, $data);

            return $decision;
        });
    }

    public function analytics(): array
    {
        $totalUsers = max(1, User::query()->count());
        $attempted = KycReviewCase::query()->distinct('user_id')->count('user_id');
        $autoApproved = DB::table('user_verifications')->where('status', 'approved')->whereNull('reviewed_by')->count();
        $queued = KycReviewCase::query()->count();
        $approved = KycDecision::query()->whereIn('action', ['approve', 'approve_note', 'award_badge'])->count();
        $final = max(1, KycDecision::query()->count());

        return [
            'funnel' => [
                ['label' => 'Signed up', 'count' => $totalUsers, 'percent' => 100],
                ['label' => 'Attempted Tier 2+', 'count' => $attempted, 'percent' => round(($attempted / $totalUsers) * 100, 1)],
                ['label' => 'Auto approved', 'count' => $autoApproved, 'percent' => round(($autoApproved / $totalUsers) * 100, 1)],
                ['label' => 'Queued for review', 'count' => $queued, 'percent' => round(($queued / $totalUsers) * 100, 1)],
                ['label' => 'Final approval rate', 'count' => $approved, 'percent' => round(($approved / $final) * 100, 1)],
            ],
            'rejection_reasons' => KycDecision::query()
                ->whereIn('action', ['reject', 'reject_investigate', 'reject_suspend'])
                ->selectRaw('reason_code, count(*) as total')
                ->groupBy('reason_code')
                ->orderByDesc('total')
                ->limit(8)
                ->get(),
            'avg_time_by_tier' => KycDecision::query()
                ->join('kyc_review_cases', 'kyc_review_cases.id', '=', 'kyc_decisions.kyc_review_case_id')
                ->selectRaw('kyc_review_cases.target_tier, avg(kyc_decisions.time_to_decision_seconds) as avg_seconds')
                ->groupBy('kyc_review_cases.target_tier')
                ->get()
                ->map(fn ($row) => ['tier' => 'Tier '.$row->target_tier, 'time' => $this->duration((int) $row->avg_seconds)]),
            'fraud_trend' => KycDecision::query()
                ->whereIn('action', ['reject_investigate', 'reject_suspend'])
                ->selectRaw("date_format(created_at, '%Y-%m') as month, count(*) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->limit(12)
                ->get(),
            'completion_by_type' => User::query()
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->selectRaw('roles.slug as role, count(*) as total, sum(case when coalesce(users.kyc_tier, users.verification_tier, 0) >= 3 then 1 else 0 end) as completed')
                ->groupBy('roles.slug')
                ->get()
                ->map(fn ($row) => ['role' => $row->role, 'rate' => round((((int) $row->completed) / max(1, (int) $row->total)) * 100, 1)]),
        ];
    }

    public function settings(): array
    {
        return [
            'active_provider' => KycSetting::value('active_provider', 'manual'),
            'fallback_provider' => KycSetting::value('fallback_provider'),
            'thresholds' => KycSetting::value('thresholds', ['nin' => 85, 'bvn' => 85, 'face_similarity' => 85]),
            'feature_gates' => KycSetting::value('feature_gates', []),
            'resubmission_limit' => KycSetting::value('resubmission_limit', 3),
            'verification_fees' => KycSetting::value('verification_fees', ['enabled' => false, 'cac_fee_minor' => 0]),
            'limits' => KycSetting::value('limits', []),
            'api_health' => ['status' => 'manual_provider', 'label' => 'Manual/API adapter pending live provider credentials'],
        ];
    }

    public function updateSettings(array $data): void
    {
        foreach ($data as $key => $value) {
            KycSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function queueRow(KycReviewCase $case): array
    {
        return [
            'id' => $case->id,
            'uuid' => $case->uuid,
            'user' => [
                'id' => $case->user?->id,
                'name' => $case->user?->name,
                'email' => $case->user?->email,
                'avatar_url' => $case->user?->avatar_url,
                'role' => $case->user?->role?->slug ?? $case->user?->account_type,
                'kyc_tier' => $case->user?->kyc_tier ?? $case->user?->verification_tier ?? 0,
            ],
            'target_tier' => $case->target_tier,
            'verification_type' => $case->verification_type,
            'status' => $case->status,
            'priority' => $case->priority,
            'queue_reason' => $case->queue_reason,
            'confidence_score' => $case->confidence_score,
            'waiting_for' => $case->entered_queue_at?->diffForHumans(),
            'entered_queue_at' => $case->entered_queue_at?->toIso8601String(),
        ];
    }

    private function applyDecision(KycReviewCase $case, string $action, array $data): void
    {
        $verification = $case->verification;
        $user = $case->user;

        if (in_array($action, ['approve', 'approve_note', 'award_badge'], true)) {
            $verification?->forceFill([
                'status' => UserVerificationStatus::Approved,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $tier = max((int) ($user->kyc_tier ?? $user->verification_tier ?? 0), (int) $case->target_tier);
            $user->forceFill(['kyc_tier' => $tier, 'verification_tier' => $tier, 'kyc_status' => 'verified', 'kyc_verified_at' => now()])->save();

            if ($action === 'approve_note' && ! empty($data['note'])) {
                AdminUserNote::query()->create([
                    'user_id' => $user->id,
                    'admin_user_id' => auth()->id(),
                    'body' => $data['note'],
                    'context' => ['source' => 'kyc_approval_note', 'kyc_case_id' => $case->id],
                ]);
            }
        }

        if ($action === 'request_correction') {
            $verification?->forceFill(['status' => UserVerificationStatus::InReview, 'rejection_reason' => $data['reason_code']])->save();
            $user->forceFill(['kyc_status' => 'action_required'])->save();
        }

        if (in_array($action, ['reject', 'reject_investigate', 'reject_suspend'], true)) {
            $verification?->forceFill(['status' => UserVerificationStatus::Rejected, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'rejection_reason' => $data['reason_code']])->save();
            $user->forceFill(['kyc_status' => $action === 'reject_investigate' ? 'under_review' : 'rejected'])->save();

            if ($action === 'reject_investigate') {
                $user->forceFill(['under_review_at' => now()])->save();
            }
            if ($action === 'reject_suspend') {
                $user->forceFill(['suspended_at' => now(), 'under_review_at' => now()])->save();
            }
        }
    }

    private function notifyUser(KycReviewCase $case, string $action, array $data): void
    {
        $title = match ($action) {
            'approve', 'approve_note', 'award_badge' => 'Your verification was approved',
            'request_correction' => 'Action needed for your verification',
            default => 'Your verification was reviewed',
        };
        $body = match ($action) {
            'approve', 'approve_note', 'award_badge' => 'Congratulations. Your account verification tier has been upgraded and more platform features are now available.',
            'request_correction' => 'Please resubmit the requested verification information: '.implode(', ', $data['correction_fields'] ?? [$data['reason_code']]),
            'reject_investigate', 'reject_suspend' => 'We could not approve your verification after review. Our team may contact you if more information is needed.',
            default => 'We could not approve your verification. Please review the guidance in your account and resubmit if appropriate.',
        };

        $case->user?->notify(new KycDecisionNotification($title, $body));
    }

    private function audit(?KycReviewCase $case, User $admin, string $event, array $metadata = []): void
    {
        KycAuditEvent::query()->create([
            'kyc_review_case_id' => $case?->id,
            'admin_user_id' => $admin->id,
            'event' => $event,
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request() ? substr((string) request()->userAgent(), 0, 2000) : null,
        ]);
    }

    private function maskSensitiveSnapshot(array $snapshot): array
    {
        foreach (['identifier_number', 'nin', 'bvn'] as $key) {
            if (! empty($snapshot[$key])) {
                $snapshot[$key] = $this->mask((string) $snapshot[$key]);
            }
        }

        return $snapshot;
    }

    private function mask(string $value): string
    {
        if (mb_strlen($value) <= 7) {
            return str_repeat('*', mb_strlen($value));
        }

        return mb_substr($value, 0, 4).str_repeat('*', max(3, mb_strlen($value) - 7)).mb_substr($value, -3);
    }

    private function duplicateContext(KycReviewCase $case): array
    {
        $identifier = data_get($case->submitted_snapshot, 'identifier_number');
        if (! $identifier) {
            return [];
        }

        return KycReviewCase::query()
            ->with('user:id,name,email,created_at,last_active_at')
            ->where('id', '!=', $case->id)
            ->where('submitted_snapshot->identifier_number', $identifier)
            ->limit(4)
            ->get()
            ->map(fn (KycReviewCase $row) => [
                'case_id' => $row->id,
                'user' => $row->user?->only(['id', 'name', 'email']),
                'registered_at' => $row->user?->created_at?->toIso8601String(),
                'last_active_at' => $row->user?->last_active_at?->toIso8601String(),
                'status' => $row->status,
            ])
            ->all();
    }

    private function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60).'m';
        }

        return floor($seconds / 3600).'h '.floor(($seconds % 3600) / 60).'m';
    }
}
