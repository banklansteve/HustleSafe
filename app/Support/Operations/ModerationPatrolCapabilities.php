<?php

namespace App\Support\Operations;

use App\Models\User;

final class ModerationPatrolCapabilities
{
    /**
     * @return array<string, mixed>
     */
    public static function forUser(?User $user): array
    {
        $isSuper = $user?->role?->slug === 'super_admin';

        return [
            'is_super_admin' => $isSuper,
            'quests' => [
                'contact_client' => true,
                'contact_freelancer' => $isSuper,
                'edit_quest' => $isSuper,
                'pause_quest' => $isSuper,
                'admin_boost' => $isSuper,
                'feature_quest' => $isSuper,
                'request_revision' => true,
                'verify_deliverables' => true,
                'investigate' => $isSuper,
                'merge_duplicate' => $isSuper,
                'collusion_check' => $isSuper,
                'dismiss_anomaly' => $isSuper,
            ],
            'proposals' => [
                'contact_freelancer' => true,
                'rate_quality' => true,
                'flag_quality' => true,
                'request_clarification' => true,
                'verify_capability' => true,
                'recommend_to_client' => $isSuper,
                'hide_from_client' => $isSuper,
                'request_hide_approval' => ! $isSuper,
                'request_revision' => $isSuper,
                'remove_proposal' => $isSuper,
                'create_template' => $isSuper,
            ],
        ];
    }
}
