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
    public function __construct(private readonly VerificationEngineService $engine) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/VerificationEngine/Index', [
            'section' => (string) $request->query('tab', 'settings'),
            'types' => $this->engine->types(),
            'levels' => $this->engine->levelRequirements(),
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
        ]);
    }

    public function updateTypes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'types' => ['required', 'array'],
            'types.*.enabled' => ['required', 'boolean'],
            'types.*.label' => ['required', 'string', 'max:120'],
            'types.*.manual_review' => ['sometimes', 'boolean'],
            'levels' => ['required', 'array'],
            'levels.*.requirements' => ['present', 'array'],
        ]);

        $old = ['types' => $this->engine->types(), 'levels' => $this->engine->levelRequirements()];
        $this->setting('verification_types', $data['types']);
        $this->setting('verification_level_requirements', $data['levels']);
        $this->engine->audit($request->user(), null, 'verification_settings.updated', $old, $data, 'Updated verification type or level requirements.');

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
            'new_account_cooldown_days' => ['required', 'integer', 'min:0', 'max:365'],
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

    public function overrideLevel(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'min:0', 'max:5'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $this->engine->overrideLevel($user, $request->user(), (int) $data['level'], $data['reason']);

        return response()->json(['ok' => true]);
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

    private function pending(Request $request)
    {
        return UserVerification::query()
            ->with(['user:id,name,email,created_at,current_verification_level,kyc_tier,verification_tier,avatar_url', 'reviewer:id,name,email', 'referredToAdmin:id,name,email'])
            ->whereIn('status', ['pending', 'in_review', 'flagged', 'unverified', 'rejected'])
            ->when($request->filled('type'), fn ($q) => $q->where(fn ($scope) => $scope->where('verification_type', $request->input('type'))->orWhere('category', $request->input('type'))))
            ->oldest('submitted_at')
            ->paginate(20, ['*'], 'pending_page')
            ->through(fn (UserVerification $verification) => [
                'id' => $verification->id,
                'type' => $verification->verification_type ?: $verification->category?->value ?: $verification->category,
                'status' => $verification->status?->value ?? $verification->status,
                'submitted_at' => $verification->submitted_at?->toIso8601String(),
                'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
                'reason' => $verification->rejection_reason,
                'concern' => $verification->admin_concern,
                'metadata' => $verification->metadata ?? [],
                'documents' => $verification->document_paths ?? [],
                'reviewer' => $verification->reviewer ? [
                    'id' => $verification->reviewer->id,
                    'name' => $verification->reviewer->name,
                    'email' => $verification->reviewer->email,
                ] : null,
                'referred_to_admin' => $verification->referredToAdmin ? [
                    'id' => $verification->referredToAdmin->id,
                    'name' => $verification->referredToAdmin->name,
                    'email' => $verification->referredToAdmin->email,
                ] : null,
                'referred_at' => $verification->referred_at?->toIso8601String(),
                'user' => [
                    'id' => $verification->user?->id,
                    'name' => $verification->user?->name,
                    'email' => $verification->user?->email,
                    'level' => $verification->user ? $this->engine->storedLevel($verification->user) : 0,
                    'account_age_days' => $verification->user?->created_at?->diffInDays(now()),
                    'avatar_url' => $verification->user?->avatar_url,
                ],
            ]);
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
