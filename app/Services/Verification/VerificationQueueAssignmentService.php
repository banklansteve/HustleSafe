<?php

namespace App\Services\Verification;

use App\Enums\UserVerificationStatus;
use App\Events\VerificationQueueChanged;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Operations\StaffVerificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class VerificationQueueAssignmentService
{
    public function __construct(
        private readonly StaffVerificationService $staffVerification,
        private readonly VerificationStaffReviewPolicy $reviewPolicy,
    ) {}

    /**
     * @return Collection<int, int>
     */
    public function onlineStaffIds(): Collection
    {
        $window = (int) config('operations.verification_queue.online_window_minutes', 5);

        return User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->where('last_active_at', '>=', now()->subMinutes($window))
            ->orderBy('id')
            ->pluck('id');
    }

    public function assignNextAvailable(UserVerification $verification): ?User
    {
        if (! $this->hasAssignmentColumns()) {
            return null;
        }

        if ($verification->assigned_staff_id) {
            return $verification->assignedStaff;
        }

        $verification->loadMissing(['user.role']);
        $requiresSuperAdmin = $this->reviewPolicy->requiresSuperAdminReview($verification);
        $eligible = $this->eligibleOnlineStaffIds($verification);

        if ($eligible->isEmpty()) {
            return null;
        }

        $loads = UserVerification::query()
            ->whereIn('assigned_staff_id', $eligible)
            ->whereIn('status', [
                UserVerificationStatus::Pending,
                UserVerificationStatus::InReview,
                UserVerificationStatus::Unverified,
            ])
            ->whereNotNull('submitted_at')
            ->selectRaw('assigned_staff_id, COUNT(*) as load_count')
            ->groupBy('assigned_staff_id')
            ->pluck('load_count', 'assigned_staff_id');

        $staffId = (int) $eligible
            ->sortBy(fn (int $id) => (int) ($loads[$id] ?? 0))
            ->first();

        $staff = User::query()->with('role:id,slug')->find($staffId);
        if ($staff === null) {
            return null;
        }

        if (! $requiresSuperAdmin && $staff->role?->slug === 'super_admin') {
            return null;
        }

        $this->staffVerification->assignToStaff($verification, $staff);

        return $staff;
    }

    public function broadcastUpdate(UserVerification $verification, string $action = 'updated'): void
    {
        if (! $this->liveBroadcastEnabled()) {
            return;
        }

        try {
            $verification->loadMissing(['user', 'assignedStaff']);
            $viewer = $verification->assignedStaff;
            if ($viewer === null && $verification->assigned_staff_id) {
                $viewer = User::query()->find((int) $verification->assigned_staff_id);
            }
            if ($viewer === null) {
                $viewer = User::query()
                    ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
                    ->orderBy('id')
                    ->first();
            }
            if ($viewer === null) {
                return;
            }

            $row = $this->staffVerification->queueRowForBroadcast($verification, $viewer);

            broadcast(new VerificationQueueChanged(
                $row,
                $action,
                (int) ($verification->assigned_staff_id ?? 0),
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function eligibleOnlineStaffIds(UserVerification $verification): Collection
    {
        $online = $this->onlineStaffIds();
        if ($online->isEmpty()) {
            return collect();
        }

        $requiresSuperAdmin = $this->reviewPolicy->requiresSuperAdminReview($verification);

        if ($requiresSuperAdmin) {
            return $online
                ->filter(fn (int $staffId) => $this->userHasRoleSlug($staffId, 'super_admin'))
                ->values();
        }

        $staffAdmins = $online
            ->filter(fn (int $staffId) => $this->userHasRoleSlug($staffId, 'admin'))
            ->values();

        return $staffAdmins;
    }

    private function userHasRoleSlug(int $userId, string $slug): bool
    {
        return User::query()
            ->whereKey($userId)
            ->whereHas('role', fn ($q) => $q->where('slug', $slug))
            ->exists();
    }

    private function liveBroadcastEnabled(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        return $driver !== '' && $driver !== 'null';
    }

    private function hasAssignmentColumns(): bool
    {
        return Schema::hasColumn('user_verifications', 'assigned_staff_id')
            && Schema::hasColumn('user_verifications', 'staff_assigned_at');
    }
}
