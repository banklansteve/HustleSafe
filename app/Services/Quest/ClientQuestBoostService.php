<?php

namespace App\Services\Quest;

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

        if ($this->hasActiveBoost($quest)) {
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

    /**
     * @return list<array<string, mixed>>
     */
    public function availableTierOptions(Quest $quest): array
    {
        $remainingHours = $this->remainingListingHours($quest);

        return collect(PlatformSettings::questBoostPricing())
            ->keys()
            ->map(fn (string $tierValue) => QuestBoostTier::from($tierValue))
            ->filter(function (QuestBoostTier $tier) use ($remainingHours): bool {
                if ($remainingHours === null) {
                    return true;
                }

                return $tier->durationHours() <= $remainingHours;
            })
            ->map(fn (QuestBoostTier $tier) => $this->tierRow($tier, $quest, $remainingHours))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function upsellPayload(Quest $quest, User $client, bool $highlightPanel = false): array
    {
        $remainingHours = $this->remainingListingHours($quest);
        $allTiers = collect(QuestBoostTier::ordered())
            ->map(fn (QuestBoostTier $tier) => $this->tierRow($tier, $quest, $remainingHours))
            ->values()
            ->all();

        $canPurchase = $this->canPurchase($quest, $client);
        $showPanel = $canPurchase
            && $quest->boost_upsell_dismissed_at === null
            && ($highlightPanel || $quest->created_at?->greaterThan(now()->subDays(3)));

        return [
            'can_purchase' => $canPurchase,
            'show_panel' => $showPanel,
            'has_active_boost' => $this->hasActiveBoost($quest),
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

    public function remainingListingHours(Quest $quest): ?float
    {
        if ($quest->status !== QuestStatus::Open || $quest->listing_expires_at === null) {
            return null;
        }

        if ($quest->listing_expires_at->lte(now())) {
            return 0.0;
        }

        return max(0.0, now()->diffInMinutes($quest->listing_expires_at, false) / 60);
    }

    public function resolveBoostEndsAt(Quest $quest, QuestBoostTier $tier): Carbon
    {
        $endsAt = now()->addHours($tier->durationHours());

        if ($quest->listing_expires_at !== null && $quest->status === QuestStatus::Open) {
            $endsAt = $endsAt->min($quest->listing_expires_at);
        }

        return $endsAt;
    }

    public function assertTierAllowed(Quest $quest, QuestBoostTier $tier): void
    {
        $remainingHours = $this->remainingListingHours($quest);

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
    private function tierRow(QuestBoostTier $tier, Quest $quest, ?float $remainingHours): array
    {
        $priceMinor = PlatformSettings::questBoostPriceMinor($tier);
        $available = $remainingHours === null || $tier->durationHours() <= $remainingHours;
        $endsAt = $this->resolveBoostEndsAt($quest, $tier);

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
