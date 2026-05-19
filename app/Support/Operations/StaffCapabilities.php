<?php

namespace App\Support\Operations;

use App\Enums\AdminProposalStatus;
use App\Enums\AdminQuestStatus;

final class StaffCapabilities
{
    /**
     * @return list<string>
     */
    public static function questAdminStatusValues(): array
    {
        return [
            AdminQuestStatus::Flagged->value,
            AdminQuestStatus::UnderReview->value,
            AdminQuestStatus::Referred->value,
            AdminQuestStatus::ActionRequired->value,
            AdminQuestStatus::Resolved->value,
            AdminQuestStatus::Suspended->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function proposalAdminStatusValues(): array
    {
        return [
            AdminProposalStatus::Flagged->value,
            AdminProposalStatus::UnderReview->value,
            AdminProposalStatus::Referred->value,
            AdminProposalStatus::ActionRequired->value,
            AdminProposalStatus::Resolved->value,
        ];
    }

    public static function canSetQuestAdminStatus(string $status): bool
    {
        return in_array($status, self::questAdminStatusValues(), true);
    }

    public static function canSetProposalAdminStatus(string $status): bool
    {
        return in_array($status, self::proposalAdminStatusValues(), true);
    }

    public static function canPermanentlyDeleteQuest(): bool
    {
        return false;
    }

    public static function canSuspendProposalWithoutApproval(): bool
    {
        return false;
    }

    public static function canPermanentlyBanUser(): bool
    {
        return false;
    }

    public static function maxSuspensionHours(): int
    {
        return 72;
    }
}
