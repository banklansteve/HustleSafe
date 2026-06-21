<?php

namespace App\Services\Quest;

use App\Enums\QuestBoostStatus;
use App\Enums\QuestBoostTier;
use App\Enums\QuestStatus;
use App\Models\AdminFinancialLedgerEntry;
use App\Models\AdminNotification;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\User;
use App\Notifications\QuestBoostUpsellNotification;
use App\Services\Admin\AdminActivityFeedService;
use App\Support\NgnMoney;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ClientQuestBoostService
{
    public function canPurchase(Quest $quest, User $client): bool
    {
        if ((int) $quest->client_id !== (int) $client->id) {
            return false;
        }

        if ($quest->status !== QuestStatus::Open) {
            return false;
        }

        if ($this->scheduledFollowOnBoost($quest) !== null) {
            return false;
        }

        // Block while a boost is active, EXCEPT when it is close to expiry — in which
        // case the owner may purchase a follow-on boost that continues seamlessly.
        if ($this->hasActiveBoost($quest) && ! $this->isWithinReboostWindow($quest)) {
            return false;
        }

        return $this->availableTierOptions($quest) !== [];
    }

    public function hasActiveBoost(Quest $quest): bool
    {
        return QuestBoost::query()
            ->where('quest_id', $quest->id)
            ->activeNow()
            ->exists();
    }

    public function activeBoost(Quest $quest): ?QuestBoost
    {
        return QuestBoost::query()
            ->where('quest_id', $quest->id)
            ->activeNow()
            ->orderBy('ends_at')
            ->first();
    }

    public function scheduledFollowOnBoost(Quest $quest): ?QuestBoost
    {
        return QuestBoost::query()
            ->where('quest_id', $quest->id)
            ->where('status', QuestBoostStatus::Active->value)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * How close to expiry (in hours) the "boost again" option appears:
     * 48h for long boosts (14/30 day), 24h for short boosts (3/7 day).
     */
    public function reboostWindowHours(QuestBoostTier $tier): int
    {
        return in_array($tier, [QuestBoostTier::FourteenDay, QuestBoostTier::ThirtyDay], true) ? 48 : 24;
    }

    public function isWithinReboostWindow(Quest $quest): bool
    {
        $active = $this->activeBoost($quest);
        if ($active === null || $active->ends_at === null) {
            return false;
        }

        $window = $this->reboostWindowHours($active->tierEnum());

        return now()->gte($active->ends_at->copy()->subHours($window));
    }

    /**
     * When the next boost should begin: right after the current one ends if
     * re-boosting near expiry, otherwise immediately.
     */
    public function boostAnchor(Quest $quest): Carbon
    {
        $active = $this->activeBoost($quest);
        if ($active?->ends_at !== null && $this->isWithinReboostWindow($quest)) {
            return $active->ends_at->copy();
        }

        return now();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function availableTierOptions(Quest $quest): array
    {
        $anchor = $this->boostAnchor($quest);
        $remainingHours = $this->remainingListingHours($quest, $anchor);

        return collect(PlatformSettings::questBoostPricing())
            ->keys()
            ->map(fn (string $tierValue) => QuestBoostTier::from($tierValue))
            ->filter(function (QuestBoostTier $tier) use ($remainingHours): bool {
                if ($remainingHours === null) {
                    return true;
                }

                return $tier->durationHours() <= $remainingHours;
            })
            ->map(fn (QuestBoostTier $tier) => $this->tierRow($tier, $quest, $remainingHours, $anchor))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function upsellPayload(Quest $quest, User $client, bool $highlightPanel = false): array
    {
        $anchor = $this->boostAnchor($quest);
        $remainingHours = $this->remainingListingHours($quest, $anchor);
        $allTiers = collect(QuestBoostTier::ordered())
            ->map(fn (QuestBoostTier $tier) => $this->tierRow($tier, $quest, $remainingHours, $anchor))
            ->values()
            ->all();

        $canPurchase = $this->canPurchase($quest, $client);
        $activeBoost = $this->activeBoost($quest);
        $scheduledBoost = $this->scheduledFollowOnBoost($quest);
        $isReboost = $this->isWithinReboostWindow($quest) && $scheduledBoost === null;
        $showPanel = $canPurchase
            && ($isReboost
                || ($quest->boost_upsell_dismissed_at === null
                    && ($highlightPanel || $quest->created_at?->greaterThan(now()->subDays(3)))))
            || $scheduledBoost !== null;

        return [
            'can_purchase' => $canPurchase,
            'show_panel' => $showPanel,
            'has_active_boost' => $this->hasActiveBoost($quest),
            'is_reboost' => $isReboost,
            'has_scheduled_follow_on' => $scheduledBoost !== null,
            'active_boost' => $activeBoost ? [
                'tier_label' => $activeBoost->tierEnum()->label(),
                'ends_at' => $activeBoost->ends_at?->timezone('Africa/Lagos')->toIso8601String(),
            ] : null,
            'scheduled_boost' => $scheduledBoost ? [
                'tier_label' => $scheduledBoost->tierEnum()->label(),
                'starts_at' => $scheduledBoost->starts_at->timezone('Africa/Lagos')->toIso8601String(),
                'ends_at' => $scheduledBoost->ends_at->timezone('Africa/Lagos')->toIso8601String(),
            ] : null,
            'listing_expires_at' => $quest->listing_expires_at?->timezone('Africa/Lagos')->toIso8601String(),
            'remaining_listing_hours' => $remainingHours,
            'remaining_listing_label' => $this->remainingListingLabel($quest, $remainingHours),
            'tiers' => $allTiers,
            'available_tiers' => array_values(array_filter($allTiers, fn (array $t) => $t['available'])),
            'checkout_url' => route('quests.boost.checkout', $quest),
            'dismiss_url' => route('quests.boost.dismiss-upsell', $quest),
            'payment_callback_route' => 'payments.quest-boost.callback',
        ];
    }

    public function remainingListingHours(Quest $quest, ?Carbon $anchor = null): ?float
    {
        if ($quest->status !== QuestStatus::Open || $quest->listing_expires_at === null) {
            return null;
        }

        $anchor ??= now();

        if ($quest->listing_expires_at->lte($anchor)) {
            return 0.0;
        }

        return max(0.0, $anchor->diffInMinutes($quest->listing_expires_at, false) / 60);
    }

    public function resolveBoostEndsAt(Quest $quest, QuestBoostTier $tier, ?Carbon $anchor = null): Carbon
    {
        $anchor ??= now();
        $endsAt = $anchor->copy()->addHours($tier->durationHours());

        if ($quest->listing_expires_at !== null && $quest->status === QuestStatus::Open) {
            $endsAt = $endsAt->min($quest->listing_expires_at);
        }

        return $endsAt;
    }

    public function assertTierAllowed(Quest $quest, QuestBoostTier $tier): void
    {
        $remainingHours = $this->remainingListingHours($quest, $this->boostAnchor($quest));

        if ($remainingHours !== null && $tier->durationHours() > $remainingHours) {
            throw ValidationException::withMessages([
                'tier' => [
                    __('This boost lasts :duration, but your quest listing closes in :remaining. Choose a shorter boost or extend your listing deadline.', [
                        'duration' => $tier->label(),
                        'remaining' => $this->remainingListingLabel($quest, $remainingHours),
                    ]),
                ],
            ]);
        }
    }

    public function dismissUpsell(Quest $quest, User $client): void
    {
        if ((int) $quest->client_id !== (int) $client->id) {
            abort(403);
        }

        $quest->forceFill(['boost_upsell_dismissed_at' => now()])->save();
    }

    public function scheduleUpsellEmail(Quest $quest): void
    {
        if ($quest->status !== QuestStatus::Open || $this->hasActiveBoost($quest)) {
            return;
        }

        $delayMinutes = max(1, (int) config('quest_boost.upsell_email_delay_minutes', 20));

        \App\Jobs\SendQuestBoostUpsellMailJob::dispatch((int) $quest->id)
            ->delay(now()->addMinutes($delayMinutes));
    }

    public function notifyUpsell(Quest $quest, User $client): void
    {
        if (! $this->canPurchase($quest, $client)) {
            return;
        }

        $client->notify(new QuestBoostUpsellNotification($quest));
    }

    public function notifyAdminsOfPurchase(QuestBoost $boost, User $client): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $amountDisplay = NgnMoney::format((int) $boost->planned_cost_minor);
        $showUrl = route('admin.quest-boosts.show', $boost, absolute: false);

        User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->pluck('id')
            ->each(function (int $adminId) use ($boost, $client, $amountDisplay, $showUrl): void {
                AdminNotification::query()->create([
                    'admin_user_id' => $adminId,
                    'category' => 'quest_boost',
                    'priority' => 'normal',
                    'title' => 'Quest boost purchased',
                    'body' => "{$client->name} paid {$amountDisplay} to boost \"{$boost->quest_title_snapshot}\".",
                    'action_label' => 'View boost',
                    'action_url' => $showUrl,
                    'data' => [
                        'dedupe_key' => "quest_boost_purchased:{$boost->id}:{$adminId}",
                        'quest_boost_id' => $boost->id,
                        'quest_id' => $boost->quest_id,
                        'client_id' => $client->id,
                    ],
                ]);
            });

        app(AdminActivityFeedService::class)->record(
            'jobs',
            'quest.boost_purchased',
            'Quest boost purchased',
            "{$client->name} purchased a boost for {$boost->quest_title_snapshot}",
            app(AdminActivityFeedService::class)->entities([
                ['type' => 'user', 'id' => $client->id, 'label' => $client->name],
                ['type' => 'quest', 'id' => $boost->quest_id, 'label' => $boost->quest_title_snapshot],
            ]),
            [
                'tier' => $boost->tierEnum()->label(),
                'amount' => $amountDisplay,
                'reference' => $boost->reference,
            ],
            (int) $boost->planned_cost_minor,
            $client,
            Quest::class,
            $boost->quest_id,
        );
    }

    public function recordClientRevenue(QuestBoost $boost, User $client): void
    {
        AdminFinancialLedgerEntry::query()->create([
            'quest_id' => $boost->quest_id,
            'client_id' => $boost->client_id,
            'type' => 'quest_boost_payment',
            'direction' => 'inflow',
            'source' => 'quest_boost',
            'status' => 'completed',
            'description' => __('Client quest boost payment'),
            'gross_amount_minor' => (int) $boost->planned_cost_minor,
            'fee_amount_minor' => (int) $boost->planned_cost_minor,
            'net_amount_minor' => (int) $boost->planned_cost_minor,
            'meta' => [
                'quest_boost_id' => $boost->id,
                'quest_boost_reference' => $boost->reference,
                'tier' => $boost->tier,
                'purchased_by_client_id' => $client->id,
            ],
            'occurred_at' => $boost->granted_at ?? now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tierRow(QuestBoostTier $tier, Quest $quest, ?float $remainingHours, ?Carbon $anchor = null): array
    {
        $priceMinor = PlatformSettings::questBoostPriceMinor($tier);
        $available = $remainingHours === null || $tier->durationHours() <= $remainingHours;
        $endsAt = $this->resolveBoostEndsAt($quest, $tier, $anchor);

        return [
            'value' => $tier->value,
            'label' => $tier->label(),
            'hours' => $tier->durationHours(),
            'days' => (int) round($tier->durationHours() / 24),
            'price_minor' => $priceMinor,
            'price_display' => NgnMoney::format($priceMinor),
            'available' => $available,
            'unavailable_reason' => $available
                ? null
                : __('Listing closes before this boost would end. Max boost is :remaining.', [
                    'remaining' => $this->remainingListingLabel($quest, $remainingHours),
                ]),
            'effective_ends_at' => $endsAt->timezone('Africa/Lagos')->toIso8601String(),
        ];
    }

    private function remainingListingLabel(Quest $quest, ?float $remainingHours): string
    {
        if ($remainingHours === null) {
            return __('the listing deadline');
        }

        if ($remainingHours <= 0) {
            return __('less than an hour');
        }

        if ($remainingHours < 24) {
            $hours = (int) ceil($remainingHours);

            return trans_choice(':count hour|:count hours', $hours, ['count' => $hours]);
        }

        $days = (int) floor($remainingHours / 24);
        $hours = (int) ceil($remainingHours - ($days * 24));

        if ($hours === 0) {
            return trans_choice(':count day|:count days', $days, ['count' => $days]);
        }

        return __(':daysd :hoursh', ['days' => $days, 'hours' => $hours]);
    }
}
