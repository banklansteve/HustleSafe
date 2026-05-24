<?php

namespace App\Support;

use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\User;

final class EscrowReleasePolicy
{
    public static function highValueAuthorizationMinor(): int
    {
        return max(0, PlatformSettings::int('financial.high_value_release_authorization_minor', 100_000_000));
    }

    public static function escrowAmountMinor(Quest $quest): int
    {
        $escrow = $quest->relationLoaded('paymentEscrow')
            ? $quest->paymentEscrow
            : PaymentEscrow::query()->where('quest_id', $quest->id)->first();

        if ($escrow !== null) {
            return (int) $escrow->amount_minor;
        }

        $quest->loadMissing('acceptedOffer');

        return (int) ($quest->acceptedOffer?->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0);
    }

    public static function requiresSuperAdminAuthorization(Quest $quest): bool
    {
        return self::escrowAmountMinor($quest) >= self::highValueAuthorizationMinor();
    }

    public static function isReleaseHeld(Quest $quest): bool
    {
        if ($quest->release_hold_until === null && blank($quest->release_hold_reason)) {
            return false;
        }

        if ($quest->release_hold_until === null) {
            return true;
        }

        return now()->lt($quest->release_hold_until);
    }

    public static function hasSuperAdminAuthorization(Quest $quest): bool
    {
        if (! self::requiresSuperAdminAuthorization($quest)) {
            return true;
        }

        return $quest->release_authorized_at !== null;
    }

    public static function canAcknowledgeDelivery(Quest $quest, ?User $client): bool
    {
        if ($client === null || (int) $quest->client_id !== (int) $client->id) {
            return false;
        }

        if (($quest->status?->value ?? (string) $quest->status) !== 'in_progress') {
            return false;
        }

        if (! in_array($quest->escrow_status, ['funded', 'partially_released'], true)) {
            return false;
        }

        return $quest->delivery_acknowledged_at === null;
    }

    public static function canReleaseFunds(Quest $quest, ?User $actor = null): bool
    {
        if (! in_array($quest->escrow_status, ['funded', 'partially_released'], true)) {
            return false;
        }

        if ($quest->delivery_acknowledged_at === null && ! EscrowReleaseCooldown::actorMayBypass($actor)) {
            return false;
        }

        if (self::isReleaseHeld($quest) && ! EscrowReleaseCooldown::actorMayBypass($actor)) {
            return false;
        }

        if (! self::hasSuperAdminAuthorization($quest) && ! EscrowReleaseCooldown::actorMayBypass($actor)) {
            return false;
        }

        return EscrowReleaseCooldown::canReleaseFunds($quest, $actor);
    }

    public static function blockedReleaseReason(Quest $quest, ?User $actor = null): ?string
    {
        if (self::canReleaseFunds($quest, $actor)) {
            return null;
        }

        if ($quest->delivery_acknowledged_at === null) {
            return __('Confirm delivery first before releasing escrow.');
        }

        if (self::isReleaseHeld($quest) && ! EscrowReleaseCooldown::actorMayBypass($actor)) {
            $until = $quest->release_hold_until?->timezone(config('app.timezone'))->format('j M Y, H:i');

            return $quest->release_hold_until
                ? __('Release is on hold until :when. Reason: :reason', ['when' => $until, 'reason' => $quest->release_hold_reason ?? '—'])
                : __('Release is on hold by platform staff. Reason: :reason', ['reason' => $quest->release_hold_reason ?? '—']);
        }

        if (! self::hasSuperAdminAuthorization($quest) && ! EscrowReleaseCooldown::actorMayBypass($actor)) {
            return __('High-value contracts (from :amount) require HustleSafe authorisation before funds can be released.', [
                'amount' => NgnMoney::format(self::highValueAuthorizationMinor()),
            ]);
        }

        return EscrowReleaseCooldown::blockedReason($quest, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public static function uiPayload(Quest $quest, ?User $viewer): array
    {
        $isClient = $viewer !== null && (int) $viewer->id === (int) $quest->client_id;
        $inProgress = ($quest->status?->value ?? (string) $quest->status) === 'in_progress';
        $funded = in_array($quest->escrow_status, ['funded', 'partially_released'], true);
        $acknowledged = $quest->delivery_acknowledged_at !== null;
        $eligibleAt = EscrowReleaseCooldown::releaseEligibleAt($quest);

        return [
            'cooldown_hours' => EscrowReleaseCooldown::cooldownHours(),
            'release_eligible_at' => $eligibleAt?->toIso8601String(),
            'seconds_until_release' => EscrowReleaseCooldown::secondsUntilEligible($quest),
            'release_eligible_label' => $eligibleAt?->timezone(config('app.timezone'))->format('j M Y, H:i'),
            'delivery_acknowledged' => $acknowledged,
            'delivery_acknowledged_at' => $quest->delivery_acknowledged_at?->toIso8601String(),
            'requires_admin_authorization' => self::requiresSuperAdminAuthorization($quest),
            'has_admin_authorization' => self::hasSuperAdminAuthorization($quest),
            'release_held' => self::isReleaseHeld($quest),
            'release_hold_reason' => $quest->release_hold_reason,
            'release_hold_until' => $quest->release_hold_until?->toIso8601String(),
            'can_acknowledge_delivery' => self::canAcknowledgeDelivery($quest, $viewer),
            'can_release_funds' => $isClient && $inProgress && $funded && self::canReleaseFunds($quest, $viewer),
            'show_completion_section' => $isClient && $inProgress && $funded,
            'blocked_release_reason' => $isClient && $acknowledged && ! self::canReleaseFunds($quest, $viewer)
                ? self::blockedReleaseReason($quest, $viewer)
                : null,
            'high_value_threshold' => NgnMoney::format(self::highValueAuthorizationMinor()),
        ];
    }
}
