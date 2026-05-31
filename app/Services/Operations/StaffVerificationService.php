<?php

namespace App\Services\Operations;

use App\Enums\UserVerificationStatus;
use App\Models\AdminTask;
use App\Models\KycReviewCase;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\AdminActivityLogger;
use App\Services\Verification\UserVerificationDecisionService;
use App\Services\Verification\UserVerificationPresentationService;
use App\Services\Verification\VerificationStaffReviewPolicy;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffVerificationService
{
    public function __construct(
        private readonly UserVerificationDecisionService $decisions,
        private readonly UserVerificationPresentationService $presentation,
        private readonly AdminActivityLogger $logger,
        private readonly VerificationStaffReviewPolicy $reviewPolicy,
    ) {}

    public function paginatedListing(Request $request, User $staff): LengthAwarePaginator
    {
        $tab = (string) $request->input('tab', 'my_assignments');
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $sort = (string) $request->input('sort', $status === 'pending' ? 'submitted_at' : 'staff_assigned_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(100, max(10, $request->integer('per_page', (int) config('operations.verification_queue.per_page', 25))));

        $query = UserVerification::query()
            ->with([
                'user:id,name,email,avatar_url,current_verification_level,verification_tier,created_at',
                'reviewer:id,name,email',
                'assignedStaff:id,name,email',
            ])
            ->whereNotNull('submitted_at');

        $this->applyTabScope($query, $tab, $staff, $request);
        $this->applyStatusFilter($query, $status, $tab);

        if ($q !== '') {
            $query->where(function (Builder $outer) use ($q): void {
                $outer->whereHas('user', function (Builder $user) use ($q): void {
                    $user->where(function (Builder $inner) use ($q): void {
                        $inner->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%");
                    });
                })->orWhere('verification_type', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            });
        }

        $type = trim((string) $request->input('type', ''));
        if ($type !== '') {
            $query->where(function (Builder $outer) use ($type): void {
                $outer->where('verification_type', $type)
                    ->orWhere('category', $type);
            });
        }

        if ($status === 'pending' && $sort === 'submitted_at') {
            $direction = 'asc';
        }

        $this->applySort($query, $sort, $direction);

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (UserVerification $verification) => $this->row($verification, $staff));
    }

    public function assignToStaff(UserVerification $verification, User $staff): UserVerification
    {
        if (! $this->hasAssignmentColumns()) {
            return $verification;
        }

        if ($verification->assigned_staff_id && (int) $verification->assigned_staff_id !== (int) $staff->id) {
            throw ValidationException::withMessages([
                'verification' => __('This verification is assigned to another team member.'),
            ]);
        }

        if (! $verification->assigned_staff_id) {
            $verification->forceFill([
                'assigned_staff_id' => $staff->id,
                'staff_assigned_at' => now(),
            ])->save();
            $this->syncKycCaseAssignment($verification, $staff);
        }

        return $verification->fresh(['user', 'reviewer', 'assignedStaff']);
    }

    public function detail(UserVerification $verification, User $staff): array
    {
        if ($this->staffCanClaim($verification)) {
            try {
                $verification = $this->assignToStaff($verification, $staff);
            } catch (ValidationException) {
                // View-only when another staff member already owns the assignment.
            }
        }

        $presentation = $this->presentation->forReview($verification, 'operations.api.verifications.document');
        $presentation['staff_can_decide'] = $this->staffCanDecide($verification, $staff);
        $presentation['requires_super_admin_review'] = $this->reviewPolicy->requiresSuperAdminReview($verification);
        $presentation['is_escalated_to_super_admin'] = $this->isEscalatedToSuperAdmin($verification);

        return [
            'verification' => $this->row($verification, $staff, true),
            'presentation' => $presentation,
            'allowed_actions' => $this->allowedActions($verification, $staff),
            'decision_reasons' => app(\App\Services\Verification\VerificationDecisionReasonService::class)->options(),
        ];
    }

    public function decide(UserVerification $verification, User $staff, array $data, Request $request): array
    {
        if ($this->hasAssignmentColumns()
            && $verification->assigned_staff_id
            && (int) $verification->assigned_staff_id !== (int) $staff->id) {
            throw ValidationException::withMessages([
                'action' => __('This verification is assigned to another team member.'),
            ]);
        }

        if ($this->staffCanClaim($verification) && ! $verification->assigned_staff_id) {
            $verification = $this->assignToStaff($verification, $staff);
        }

        if (! $this->staffCanDecide($verification, $staff)) {
            throw ValidationException::withMessages([
                'action' => $this->reviewPolicy->requiresSuperAdminReview($verification)
                    ? __('This verification tier is reviewed by Super Admin only.')
                    : __('This verification is with Super Admin. You can review it again once it is returned to the queue.'),
            ]);
        }

        return $this->decisions->decide($verification, $staff, $data, $request, 'operations.verification');
    }

    /**
     * @return array{presentation: array<string, mixed>, message: string}
     */
    public function escalate(UserVerification $verification, User $staff, array $data, Request $request): array
    {
        if (! Schema::hasTable('admin_tasks')) {
            throw ValidationException::withMessages(['escalate' => 'Task system unavailable.']);
        }

        $superAdmin = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->orderBy('id')
            ->first();

        if ($superAdmin === null) {
            throw ValidationException::withMessages(['escalate' => 'No Super Admin available for escalation.']);
        }

        AdminTask::query()->create([
            'created_by_admin_id' => $staff->id,
            'assigned_to_admin_id' => $superAdmin->id,
            'source_type' => UserVerification::class,
            'source_id' => $verification->id,
            'title' => 'Escalated KYC · '.$verification->user?->name,
            'description' => $data['reason'],
            'priority' => 'high',
            'status' => 'todo',
            'due_at' => now()->addDay(),
        ]);

        $verification->forceFill($this->reviewPatch([
            'status' => UserVerificationStatus::Flagged,
            'admin_concern' => $data['reason'],
            'referred_to_admin_id' => $superAdmin->id,
            'referred_at' => now(),
        ]))->save();

        $this->logger->log($staff, 'operations.verification.escalated', UserVerification::class, $verification->id, $data, $request);

        $fresh = $verification->fresh(['user', 'reviewer', 'assignedStaff']);
        $presentation = $this->presentation->forReview($fresh, 'operations.api.verifications.document');
        $presentation['staff_can_decide'] = false;
        $presentation['is_escalated_to_super_admin'] = true;

        return [
            'presentation' => $presentation,
            'message' => __('Verification escalated to Super Admin.'),
        ];
    }

    public function staffCanDecide(UserVerification $verification, User $staff): bool
    {
        $status = $verification->status?->value ?? (string) $verification->status;

        if (! in_array($status, [
            UserVerificationStatus::Pending->value,
            UserVerificationStatus::InReview->value,
            UserVerificationStatus::Unverified->value,
        ], true)) {
            return false;
        }

        if ($this->isEscalatedToSuperAdmin($verification)) {
            return false;
        }

        return $this->reviewPolicy->staffCanReview($verification, $staff);
    }

    public function isEscalatedToSuperAdmin(UserVerification $verification): bool
    {
        $status = $verification->status?->value ?? (string) $verification->status;

        if ($status === UserVerificationStatus::Flagged->value) {
            return true;
        }

        return $this->hasOpenEscalationTask($verification);
    }

    /**
     * @return list<string>
     */
    private function allowedActions(UserVerification $verification, User $staff): array
    {
        return $this->staffCanDecide($verification, $staff)
            ? ['approve', 'reject', 'request_corrections', 'escalate']
            : [];
    }

    private function staffCanClaim(UserVerification $verification): bool
    {
        $status = $verification->status?->value ?? (string) $verification->status;

        return in_array($status, [
            UserVerificationStatus::Pending->value,
            UserVerificationStatus::InReview->value,
            UserVerificationStatus::Unverified->value,
        ], true);
    }

    private function applyTabScope(Builder $query, string $tab, User $staff, Request $request): void
    {
        $tz = config('app.timezone');

        if ($tab === 'assigned_today') {
            if ($this->hasAssignmentColumns()) {
                $query->where('assigned_staff_id', $staff->id)
                    ->whereDate('staff_assigned_at', now($tz)->toDateString());
            } else {
                $query->where('reviewed_by', $staff->id)
                    ->whereDate('reviewed_at', now($tz)->toDateString());
            }

            return;
        }

        if ($tab === 'pending_queue') {
            app(VerificationStaffReviewPolicy::class)->staffQueueQuery($query, $staff);

            return;
        }

        if ($tab === 'my_assignments' && trim((string) $request->input('q', '')) !== '') {
            return;
        }

        // my_assignments (default)
        [$from, $to] = $this->assignmentDateRange($request);

        if ($this->hasAssignmentColumns()) {
            $query->where(function (Builder $scope) use ($staff, $from, $to): void {
                $scope->where(function (Builder $assigned) use ($staff, $from, $to): void {
                    $assigned->where('assigned_staff_id', $staff->id)
                        ->whereBetween('staff_assigned_at', [$from, $to]);
                })->orWhere(function (Builder $reviewed) use ($staff, $from, $to): void {
                    $reviewed->where('reviewed_by', $staff->id)
                        ->whereBetween('reviewed_at', [$from, $to]);
                });
            });

            return;
        }

        $query->where('reviewed_by', $staff->id)
            ->whereBetween('reviewed_at', [$from, $to]);
    }

    private function applyStatusFilter(Builder $query, string $status, string $tab): void
    {
        if ($tab === 'pending_queue') {
            $statuses = $status !== ''
                ? [$status]
                : [
                    UserVerificationStatus::Pending->value,
                    UserVerificationStatus::InReview->value,
                    UserVerificationStatus::Unverified->value,
                ];

            $query->whereIn('status', $statuses);

            return;
        }

        if ($status !== '') {
            $query->where('status', $status);

            return;
        }

        $query->whereIn('status', [
            UserVerificationStatus::Pending->value,
            UserVerificationStatus::InReview->value,
            UserVerificationStatus::Flagged->value,
            UserVerificationStatus::Unverified->value,
            UserVerificationStatus::Verified->value,
            UserVerificationStatus::Approved->value,
            UserVerificationStatus::Rejected->value,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function assignmentDateRange(Request $request): array
    {
        $tz = config('app.timezone');
        $preset = (string) $request->input('range', '30d');
        $maxDays = (int) config('operations.verification_queue.max_assignment_range_days', 365);

        if ($preset === 'custom') {
            $from = $request->date('date_from', 'Y-m-d', $tz)?->startOfDay()
                ?? now($tz)->subDays(30)->startOfDay();
            $to = $request->date('date_to', 'Y-m-d', $tz)?->endOfDay()
                ?? now($tz)->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            if ($from->diffInDays($to) > $maxDays) {
                $from = $to->copy()->subDays($maxDays)->startOfDay();
            }

            return [$from, $to];
        }

        $days = match ($preset) {
            '7d' => 7,
            '90d' => 90,
            default => (int) config('operations.verification_queue.default_assignment_range_days', 30),
        };

        return [
            now($tz)->subDays($days)->startOfDay(),
            now($tz)->endOfDay(),
        ];
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $column = match ($sort) {
            'type' => 'verification_type',
            'user' => 'user',
            'status' => 'status',
            'reviewed_at' => 'reviewed_at',
            'staff_assigned_at' => 'staff_assigned_at',
            default => 'submitted_at',
        };

        if ($column === 'user') {
            $query->leftJoin('users as verification_users', 'verification_users.id', '=', 'user_verifications.user_id')
                ->orderBy('verification_users.name', $direction)
                ->select('user_verifications.*');

            return;
        }

        if ($column === 'staff_assigned_at' && ! $this->hasAssignmentColumns()) {
            $column = 'reviewed_at';
        }

        $query->orderBy($column, $direction);
    }

    private function hasAssignmentColumns(): bool
    {
        return Schema::hasColumn('user_verifications', 'assigned_staff_id')
            && Schema::hasColumn('user_verifications', 'staff_assigned_at');
    }

    private function hasOpenEscalationTask(UserVerification $verification): bool
    {
        if (! Schema::hasTable('admin_tasks')) {
            return false;
        }

        return AdminTask::query()
            ->where('source_type', UserVerification::class)
            ->where('source_id', $verification->id)
            ->whereIn('status', ['todo', 'in_progress'])
            ->exists();
    }

    /**
     * Legacy KYC cases still linked to verifications.
     */
    private function syncKycCaseAssignment(UserVerification $verification, User $staff): void
    {
        if (! Schema::hasTable('kyc_review_cases')) {
            return;
        }

        KycReviewCase::query()
            ->where('user_verification_id', $verification->id)
            ->whereNull('assigned_admin_id')
            ->update([
                'assigned_admin_id' => $staff->id,
                'review_started_at' => now(),
            ]);
    }

    private function row(UserVerification $verification, User $staff, bool $expanded = false): array
    {
        $verification->loadMissing([
            'user:id,name,email,avatar_url,current_verification_level,verification_tier,created_at',
            'reviewer:id,name,email',
            'assignedStaff:id,name,email',
        ]);
        $review = $this->presentation->forReview($verification, 'operations.api.verifications.document');

        $base = [
            'id' => $verification->id,
            'type' => $verification->verification_type ?: $verification->category?->value ?: (string) $verification->category,
            'type_label' => $review['verification_type_label'],
            'status' => $verification->status?->value ?? (string) $verification->status,
            'status_label' => $review['status_label'],
            'is_escalated' => $review['is_escalated'],
            'is_escalated_to_super_admin' => $this->isEscalatedToSuperAdmin($verification),
            'requires_super_admin_review' => $this->reviewPolicy->requiresSuperAdminReview($verification),
            'staff_can_decide' => $this->staffCanDecide($verification, $staff),
            'submitted_at' => $verification->submitted_at?->toIso8601String(),
            'submitted_at_label' => \App\Support\FormatsHumanDateTime::format($verification->submitted_at, config('app.timezone')),
            'staff_assigned_at' => $verification->staff_assigned_at?->toIso8601String(),
            'staff_assigned_at_label' => \App\Support\FormatsHumanDateTime::format($verification->staff_assigned_at, config('app.timezone')),
            'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
            'reviewed_at_label' => \App\Support\FormatsHumanDateTime::format($verification->reviewed_at, config('app.timezone')),
            'assigned_staff' => $verification->assignedStaff ? [
                'id' => $verification->assignedStaff->id,
                'name' => $verification->assignedStaff->name,
            ] : null,
            'reviewer' => $verification->reviewer ? [
                'id' => $verification->reviewer->id,
                'name' => $verification->reviewer->name,
            ] : null,
            'reason' => $verification->rejection_reason,
            'concern' => $verification->admin_concern,
            'user' => $verification->user ? [
                'id' => $verification->user->id,
                'name' => $verification->user->name,
                'email' => $verification->user->email,
                'level' => $verification->user->current_verification_level ?? $verification->user->verification_tier ?? 0,
            ] : null,
        ];

        if (! $expanded) {
            return $base;
        }

        return array_merge($base, [
            'presentation' => $review,
        ]);
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function reviewPatch(array $patch): array
    {
        foreach (['admin_concern', 'referred_to_admin_id', 'referred_at', 'assigned_staff_id', 'staff_assigned_at'] as $column) {
            if (! Schema::hasColumn('user_verifications', $column)) {
                unset($patch[$column]);
            }
        }

        return $patch;
    }
}
