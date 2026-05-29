<?php

namespace App\Services\Hr;

use App\Models\StaffRoleAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;

class StaffRoleAccessService
{
    private const GROUP_ROUTE_MAP = [
        'group_a_chat_communications' => [
            'operations.customer-support',
            'operations.support',
            'operations.team-chat',
            'operations.api.customer-support',
            'operations.api.support',
            'operations.api.team-chat',
            'operations.api.messenger',
            'operations.communications-log',
            'operations.api.communications-log',
        ],
        'group_b_moderation_operations' => [
            'operations.onboarding-quality',
            'operations.moderation',
            'operations.portfolio-review',
            'operations.reviews',
            'operations.review-integrity',
            'operations.patrol',
            'operations.badge-requests',
            'operations.api.onboarding-quality',
            'operations.api.moderation',
            'operations.api.reviews',
            'operations.api.review-integrity',
            'operations.api.patrol',
            'operations.api.badge-requests',
        ],
        'group_c_people_trust_management' => [
            'operations.users',
            'operations.verifications',
            'operations.trust',
            'operations.conversation-monitoring',
            'operations.quality',
            'operations.onboarding',
            'operations.api.users',
            'operations.api.verifications',
            'operations.api.trust',
            'operations.api.conversation-monitoring',
            'operations.api.quality',
            'operations.api.onboarding',
        ],
        'group_d_financial_disputes_casework' => [
            'operations.disputes',
            'operations.escrow-anomalies',
            'operations.sanction-appeals',
            'operations.payment-monitoring',
            'operations.payments',
            'operations.payout-exceptions',
            'operations.api.disputes',
            'operations.api.escrow-anomalies',
            'operations.api.sanction-appeals',
            'operations.api.payment-monitoring',
            'operations.api.payments',
            'operations.api.payout-exceptions',
        ],
    ];

    public function activeAssignmentFor(User $user): ?StaffRoleAssignment
    {
        $today = Carbon::today();

        return StaffRoleAssignment::query()
            ->where('staff_user_id', $user->id)
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today);
            })
            ->latest('id')
            ->first();
    }

    public function canAccessRoute(User $user, string $routeName): bool
    {
        if ($routeName === '' || str_starts_with($routeName, 'operations.notifications') || str_starts_with($routeName, 'operations.api.notifications')) {
            return true;
        }

        if (str_starts_with($routeName, 'operations.hr.') || str_starts_with($routeName, 'operations.account.')) {
            return true;
        }

        $assignment = $this->activeAssignmentFor($user);
        if ($assignment === null) {
            return false;
        }

        $allowedPrefixes = self::GROUP_ROUTE_MAP[$assignment->role_group] ?? [];
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
