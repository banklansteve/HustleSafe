<?php

namespace App\Support;

use App\Models\Quest;
use App\Models\User;
use Carbon\Carbon;

final class EscrowReleaseCooldown
{
    public static function cooldownHours(): int
    {
        return PlatformSettings::escrowReleaseCooldownHours();
    }

    public static function releaseEligibleAt(Quest $quest): ?Carbon
    {
        if ($quest->escrow_funded_at === null) {
            return null;
        }

        return $quest->escrow_funded_at->copy()->addHours(self::cooldownHours());
    }

    public static function actorMayBypass(?User $actor): bool
    {
        return $actor !== null && (string) ($actor->role?->slug ?? '') === 'super_admin';
    }

    public static function canReleaseFunds(Quest $quest, ?User $actor = null): bool
    {
        if (self::cooldownHours() === 0) {
            return $quest->escrow_funded_at !== null;
        }

        if (self::actorMayBypass($actor)) {
            return true;
        }

        $eligibleAt = self::releaseEligibleAt($quest);

        return $eligibleAt !== null && now()->gte($eligibleAt);
    }

    public static function secondsUntilEligible(Quest $quest): int
    {
        $eligibleAt = self::releaseEligibleAt($quest);
        if ($eligibleAt === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($eligibleAt, false));
    }

    public static function blockedReason(Quest $quest, ?User $actor = null): ?string
    {
        if (self::canReleaseFunds($quest, $actor)) {
            return null;
        }

        if ($quest->escrow_funded_at === null) {
            return __('Escrow must be funded before funds can be released.');
        }

        $eligibleAt = self::releaseEligibleAt($quest);

        return __('Funds can be released after :when (:hours-hour safeguard after escrow funding).', [
            'when' => $eligibleAt?->timezone(config('app.timezone'))->format('j M Y, H:i'),
            'hours' => self::cooldownHours(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function uiPayload(Quest $quest, ?User $viewer): array
    {
        $isClient = $viewer !== null && (int) $viewer->id === (int) $quest->client_id;
        $inProgress = ($quest->status?->value ?? (string) $quest->status) === 'in_progress';
        $funded = in_array($quest->escrow_status, ['funded', 'partially_released'], true);
        $canMark = $isClient && $inProgress && $funded;
        $eligible = self::canReleaseFunds($quest, $viewer);
        $eligibleAt = self::releaseEligibleAt($quest);

        return [
            'cooldown_hours' => self::cooldownHours(),
            'release_eligible_at' => $eligibleAt?->toIso8601String(),
            'seconds_until_release' => self::secondsUntilEligible($quest),
            'can_mark_complete' => $canMark && $eligible,
            'show_complete_section' => $canMark,
            'blocked_reason' => $canMark && ! $eligible ? self::blockedReason($quest, $viewer) : null,
            'release_eligible_label' => $eligibleAt?->timezone(config('app.timezone'))->format('j M Y, H:i'),
        ];
    }
}
