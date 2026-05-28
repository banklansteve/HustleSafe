<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use App\Models\KycSetting;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\VerificationAnomalyFlag;
use App\Models\VerificationEngineAuditLog;
use App\Services\Admin\AdminVerificationQueueService;
use App\Services\Verification\UserVerificationDecisionService;
use App\Services\Verification\UserVerificationPresentationService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminVerificationEngineController extends Controller
{
    public function __construct(
        private readonly VerificationEngineService $engine,
        private readonly UserVerificationPresentationService $presentation,
        private readonly AdminVerificationQueueService $verificationQueue,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/VerificationEngine/Index', [
            'section' => (string) $request->query('tab', 'settings'),
            'types' => $this->engine->types(),
            'levels' => $this->engine->clientLevelRequirements(),
            'client_levels' => $this->engine->clientLevelRequirements(),
            'freelancer_levels' => $this->engine->freelancerLevelRequirements(),
            'stage_content' => $this->engine->stageContent(),
            'limits' => $this->engine->limits(),
            'safeguards' => $this->engine->safeguards(),
            'levelCounts' => User::query()
                ->selectRaw('coalesce(current_verification_level, kyc_tier, verification_tier, 0) as level, count(*) as total')
                ->groupBy('level')
                ->pluck('total', 'level'),
            'staffAdmins' => User::query()
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $admin) => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                ]),
            'pending' => fn () => $this->pending($request),
            'anomalies' => fn () => $this->anomalies($request),
            'audit' => fn () => $this->audit($request),
            'decision_reasons' => app(\App\Services\Verification\VerificationDecisionReasonService::class)->options(),
        ]);
    }

    public function updateTypes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'types' => ['required', 'array'],
            'types.*.enabled' => ['required', 'boolean'],
            'types.*.label' => ['required', 'string', 'max:120'],
            'types.*.manual_review' => ['sometimes', 'boolean'],
            'client_levels' => ['required', 'array'],
            'client_levels.*.requirements' => ['present', 'array'],
            'client_levels.*.label' => ['sometimes', 'string', 'max:160'],
            'freelancer_levels' => ['required', 'array'],
            'freelancer_levels.*.requirements' => ['present', 'array'],
            'freelancer_levels.*.label' => ['sometimes', 'string', 'max:160'],
            'stage_content' => ['nullable', 'array'],
        ]);

        $old = [
            'types' => $this->engine->types(),
            'client_levels' => $this->engine->clientLevelRequirements(),
            'freelancer_levels' => $this->engine->freelancerLevelRequirements(),
            'stage_content' => $this->engine->stageContent(),
        ];
        $this->setting('verification_types', $data['types']);
        $this->setting('verification_client_level_requirements', $data['client_levels']);
        $this->setting('verification_freelancer_level_requirements', $data['freelancer_levels']);
        $this->setting('verification_level_requirements', $data['client_levels']);
        if (isset($data['stage_content'])) {
            $this->setting('verification_stage_content', $data['stage_content']);
        }
        $this->engine->audit($request->user(), null, 'verification_settings.updated', $old, $data, 'Updated verification types, level requirements, or stage content.');

        return back()->with('success', 'Verification settings updated.');
    }

    public function updateLimits(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_posting_minor' => ['required', 'array'],
            'freelancer_proposal_minor' => ['required', 'array'],
            'client_posting_minor.*' => ['required', 'integer', 'min:0'],
            'freelancer_proposal_minor.*' => ['required', 'integer', 'min:0'],
        ]);

        $old = $this->engine->limits();
        $this->setting('verification_limits', $data);
        $this->engine->audit($request->user(), null, 'verification_limits.updated', $old, $data, 'Updated tier limit configuration.');

        return back()->with('success', 'Verification limits saved.');
    }

    public function updateSafeguards(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'escrow_enforcement_threshold_minor' => ['required', 'integer', 'min:0'],
            'milestone_enforcement_threshold_minor' => ['required', 'integer', 'min:0'],
            'minimum_milestone_count' => ['required', 'integer', 'min:1', 'max:20'],
            'quest_repost_limit' => ['required', 'integer', 'min:0', 'max:20'],
            'high_value_arbitration_threshold_minor' => ['required', 'integer', 'min:0'],
            'anomaly_new_account_days' => ['required', 'integer', 'min:0', 'max:90'],
            'anomaly_near_ceiling_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'anomaly_verification_window_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'anomaly_high_value_minor' => ['required', 'integer', 'min:0'],
            'anomaly_proposal_burst_count' => ['required', 'integer', 'min:1', 'max:100'],
            'anomaly_proposal_burst_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'rapid_completion_high_value_minor' => ['required', 'integer', 'min:0'],
        ]);

        $old = $this->engine->safeguards();
        $this->setting('verification_safeguards', $data);
        $this->engine->audit($request->user(), null, 'verification_safeguards.updated', $old, $data, 'Updated safeguard configuration.');

        return back()->with('success', 'Safeguards saved.');
    }

    public function decide(Request $request, UserVerification $verification): JsonResponse
    {
        if ($request->filled('action')) {
            $data = $request->validate([
                'action' => ['required', Rule::in(['approve', 'reject', 'request_corrections'])],
                'reason_code' => ['required_unless:action,approve', 'nullable', 'string', 'max:40'],
                'reason_note' => ['nullable', 'string', 'max:2000'],
                'reason' => ['nullable', 'string', 'max:2000'],
            ]);

            $result = $this->decisions->decide($verification, $request->user(), $data, $request, 'admin.verification-engine');

            return response()->json(array_merge(['ok' => true], $result));
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'unverified', 'flagged'])],
            'reason' => [Rule::requiredIf($request->input('status') !== 'verified'), 'nullable', 'string', 'min:8', 'max:1000'],
            'concern' => ['nullable', 'string', 'max:2000'],
            'referred_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $old = $verification->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'admin_concern', 'referred_to_admin_id', 'referred_at']);
        $status = match ($data['status']) {
            'verified' => UserVerificationStatus::Verified,
            'flagged' => UserVerificationStatus::Flagged,
            default => UserVerificationStatus::Unverified,
        };

        $verification->forceFill($this->verificationReviewPatch([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['status'] === 'verified' ? null : $data['reason'],
            'admin_concern' => $data['concern'] ?? null,
            'referred_to_admin_id' => $data['referred_to_admin_id'] ?? null,
            'referred_at' => filled($data['referred_to_admin_id'] ?? null) ? now() : null,
        ]))->save();

        $this->completeEscalationTasks($verification);

        $verification->loadMissing('user');
        if ($verification->user) {
            $this->engine->recalculate($verification->user, $request->user(), 'Admin verification review decision.');
        }
        $this->createRegularisationTask($verification, $request->user(), $data);
        $this->engine->audit($request->user(), $verification->user, 'verification.document_reviewed', $old, $verification->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'admin_concern', 'referred_to_admin_id', 'referred_at']), $data['reason'] ?? null, $verification);

        return response()->json(['ok' => true, 'message' => 'Verification document review saved.']);
    }

    public function bulkDecide(Request $request): JsonResponse
    {
        $data = $request->validate([
            'verification_ids' => ['required', 'array', 'min:1', 'max:100'],
            'verification_ids.*' => ['integer', 'exists:user_verifications,id'],
            'status' => ['required', Rule::in(['verified', 'unverified', 'flagged'])],
            'reason' => [Rule::requiredIf($request->input('status') !== 'verified'), 'nullable', 'string', 'min:8', 'max:1000'],
            'concern' => ['nullable', 'string', 'max:2000'],
            'referred_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $verifications = UserVerification::query()->with('user')->whereKey($data['verification_ids'])->get();
        DB::transaction(function () use ($verifications, $request, $data): void {
            foreach ($verifications as $verification) {
                $status = match ($data['status']) {
                    'verified' => UserVerificationStatus::Verified,
                    'flagged' => UserVerificationStatus::Flagged,
                    default => UserVerificationStatus::Unverified,
                };

                $verification->forceFill($this->verificationReviewPatch([
                    'status' => $status,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => $data['status'] === 'verified' ? null : $data['reason'],
                    'admin_concern' => $data['concern'] ?? null,
                    'referred_to_admin_id' => $data['referred_to_admin_id'] ?? null,
                    'referred_at' => filled($data['referred_to_admin_id'] ?? null) ? now() : null,
                ]))->save();
                $this->completeEscalationTasks($verification);
                if ($verification->user) {
                    $this->engine->recalculate($verification->user, $request->user(), 'Bulk verification review decision.');
                }
                $this->createRegularisationTask($verification, $request->user(), $data);
            }
        });

        return response()->json(['ok' => true, 'affected' => $verifications->count(), 'message' => "{$verifications->count()} verification record(s) updated."]);
    }

    public function anomalyAction(Request $request, VerificationAnomalyFlag $flag): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['clear', 'restrict', 'escalate'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $flag->loadMissing('user');
        $old = $flag->only(['status', 'resolution_note']);
        if ($data['action'] === 'restrict') {
            $flag->user?->forceFill(['verification_restricted_at' => now(), 'verification_restriction_reason' => $data['reason']])->save();
        }

        $flag->forceFill([
            'status' => $data['action'] === 'clear' ? 'cleared' : $data['action'].'ed',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolution_note' => $data['reason'],
        ])->save();

        $this->engine->audit($request->user(), $flag->user, 'anomaly_flag.'.$data['action'], $old, $flag->only(['status', 'resolution_note']), $data['reason'], $flag);

        return response()->json(['ok' => true]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        $search = '%'.str_replace(['%', '_'], '', $q).'%';
        $users = User::query()
            ->with('role:id,slug')
            ->where(fn ($scope) => $scope
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search))
            ->orderBy('name')
            ->limit(15)
            ->get([
                'id',
                'name',
                'email',
                'account_type',
                'current_verification_level',
                'kyc_tier',
                'verification_tier',
                'verification_level_override',
            ]);

        return response()->json([
            'users' => $users->map(function (User $user) {
                $level = $this->engine->storedLevel($user);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->slug ?? $user->account_type,
                    'current_level' => $level,
                    'current_label' => $this->engine->levelLabel($level, $user),
                ];
            })->values(),
        ]);
    }

    public function overrideLevel(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:0', 'max:5'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $this->engine->overrideLevel($user, $request->user(), (int) $data['level'], $data['reason']);

        return response()->json([
            'ok' => true,
            'message' => __('Verification level updated for :name.', ['name' => $user->name]),
            'user' => [
                'id' => $user->id,
                'current_level' => $this->engine->storedLevel($user->fresh()),
                'current_label' => $this->engine->levelLabel($this->engine->storedLevel($user->fresh()), $user->fresh()),
            ],
        ]);
    }

    public function overrideLimits(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'client_limit_minor' => ['nullable', 'integer', 'min:0'],
            'freelancer_limit_minor' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $this->engine->resetUserLimit($user, $request->user(), $data['client_limit_minor'] ?? null, $data['freelancer_limit_minor'] ?? null, $data['reason']);

        return response()->json(['ok' => true]);
    }

    public function accountTimeline(Request $request, User $user): JsonResponse
    {
        $typeKey = trim((string) $request->query('type_key', ''));

        return response()->json($this->verificationQueue->accountTimeline(
            $user,
            $typeKey !== '' ? $typeKey : null,
        ));
    }

    private function pending(Request $request)
    {
        return $this->verificationQueue
            ->paginatedQueue($request)
            ->withQueryString();
    }

    private function anomalies(Request $request)
    {
        return VerificationAnomalyFlag::query()
            ->with('user:id,name,email,created_at,current_verification_level,kyc_tier,verification_tier,avatar_url')
            ->when($request->filled('flag_type'), fn ($q) => $q->where('type', $request->input('flag_type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(20, ['*'], 'flag_page')
            ->through(fn (VerificationAnomalyFlag $flag) => [
                'id' => $flag->id,
                'type' => $flag->type,
                'status' => $flag->status,
                'severity' => $flag->severity,
                'context' => $flag->context ?? [],
                'created_at' => $flag->created_at?->toIso8601String(),
                'user' => [
                    'id' => $flag->user?->id,
                    'name' => $flag->user?->name,
                    'email' => $flag->user?->email,
                    'level' => $flag->user ? $this->engine->storedLevel($flag->user) : 0,
                    'account_age_days' => $flag->user?->created_at?->diffInDays(now()),
                    'avatar_url' => $flag->user?->avatar_url,
                ],
            ]);
    }

    private function audit(Request $request)
    {
        return VerificationEngineAuditLog::query()
            ->with(['actor:id,name,email', 'affectedUser:id,name,email'])
            ->latest()
            ->paginate(30, ['*'], 'audit_page')
            ->through(fn (VerificationEngineAuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->actor?->name,
                'affected_user' => $log->affectedUser?->name,
                'action' => $log->action,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'reason' => $log->reason,
                'created_at' => $log->created_at?->toIso8601String(),
                'created_at_label' => \App\Support\FormatsHumanDateTime::format($log->created_at, config('app.timezone')),
            ]);
    }

    private function setting(string $key, array $value): void
    {
        KycSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function verificationReviewPatch(array $patch): array
    {
        foreach (['admin_concern', 'referred_to_admin_id', 'referred_at'] as $column) {
            if (! Schema::hasColumn('user_verifications', $column)) {
                unset($patch[$column]);
            }
        }

        return $patch;
    }

    private function completeEscalationTasks(UserVerification $verification): void
    {
        if (! Schema::hasTable('admin_tasks')) {
            return;
        }

        AdminTask::query()
            ->where('source_type', UserVerification::class)
            ->where('source_id', $verification->id)
            ->whereIn('status', ['todo', 'in_progress'])
            ->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createRegularisationTask(UserVerification $verification, User $actor, array $data): void
    {
        if (! Schema::hasTable('admin_tasks') || blank($data['referred_to_admin_id'] ?? null)) {
            return;
        }

        $verification->loadMissing('user');

        AdminTask::query()->updateOrCreate([
            'source_type' => UserVerification::class,
            'source_id' => $verification->id,
            'status' => 'todo',
        ], [
            'title' => 'Regularise '.$verification->user?->name.' verification document',
            'description' => trim(($data['concern'] ?? '') ?: ($data['reason'] ?? 'Follow up with user about verification document concerns.')),
            'priority' => $data['status'] === 'flagged' ? 'high' : 'medium',
            'assigned_to_admin_id' => $data['referred_to_admin_id'],
            'created_by_admin_id' => $actor->id,
            'due_at' => now()->addDay(),
        ]);
    }
}
