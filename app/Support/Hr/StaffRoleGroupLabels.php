<?php

namespace App\Support\Hr;

class StaffRoleGroupLabels
{
    public static function label(string $group): string
    {
        return match ($group) {
            'group_a_chat_communications' => 'Group A - Chat & Communications',
            'group_b_moderation_operations' => 'Group B - Moderation Operations',
            'group_c_people_trust_management' => 'Group C - People & Trust Management',
            'group_d_financial_disputes_casework' => 'Group D - Financial, Disputes & Casework',
            default => $group,
        };
    }
}
