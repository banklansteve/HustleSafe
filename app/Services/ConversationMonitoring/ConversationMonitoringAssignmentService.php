<?php

namespace App\Services\ConversationMonitoring;

use App\Enums\StaffRoleGroup;
use App\Models\ConversationThreadReview;
use App\Models\StaffRoleAssignment;
use App\Models\User;
use App\Notifications\ConversationMonitoringAssignedNotification;
use App\Notifications\ConversationMonitoringFlaggedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ConversationMonitoringAssignmentService
{
    /**
     * @return Collection<int, User>
     */
    public function eligibleModerators(): Collection
    {
        $today = Carbon::today();

        $staffIds = StaffRoleAssignment::query()
            ->where('status', 'active')
            ->where('role_group', StaffRoleGroup::GroupBModerationOperations->value)
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today);
            })
            ->pluck('staff_user_id')
            ->unique()
            ->filter();

        if ($staffIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $staffIds)
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function autoAssignIfNeeded(ConversationThreadReview $review): void
    {
        if ($review->assigned_staff_id || $review->status === 'awaiting_super_admin') {
            return;
        }

        $moderators = $this->eligibleModerators();
        if ($moderators->isEmpty()) {
            return;
        }

        $counts = ConversationThreadReview::query()
            ->selectRaw('assigned_staff_id, COUNT(*) as workload')
            ->whereIn('status', ['pending', 'assigned'])
            ->whereNotNull('assigned_staff_id')
            ->groupBy('assigned_staff_id')
            ->pluck('workload', 'assigned_staff_id');

        /** @var User $pick */
        $pick = $moderators->sortBy(fn (User $user) => (int) ($counts[$user->id] ?? 0))->first();

        $review->update([
            'assigned_staff_id' => $pick->id,
            'status' => 'assigned',
        ]);

        $pick->notify(new ConversationMonitoringAssignedNotification($review->fresh(['quest'])));
    }

    public function notifyAssignedOfNewFlag(ConversationThreadReview $review): void
    {
        if (! $review->assigned_staff_id) {
            return;
        }

        $staff = User::query()->find($review->assigned_staff_id);
        if ($staff) {
            $staff->notify(new ConversationMonitoringFlaggedNotification($review->fresh(['quest'])));
        }
    }

    public function afterReviewSynced(ConversationThreadReview $review, bool $isNewFlagOnExistingReview): void
    {
        $review = $review->fresh();

        if (! $review->assigned_staff_id) {
            $this->autoAssignIfNeeded($review);

            return;
        }

        if ($isNewFlagOnExistingReview) {
            $this->notifyAssignedOfNewFlag($review);
        }
    }
}
