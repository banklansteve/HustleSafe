<?php

namespace App\Enums;

enum StaffRoleGroup: string
{
    case GroupAChatCommunications = 'group_a_chat_communications';
    case GroupBModerationOperations = 'group_b_moderation_operations';
    case GroupCPeopleTrustManagement = 'group_c_people_trust_management';
    case GroupDFinancialDisputesCasework = 'group_d_financial_disputes_casework';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
