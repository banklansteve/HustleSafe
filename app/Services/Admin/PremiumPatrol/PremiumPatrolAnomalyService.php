<?php

namespace App\Services\Admin\PremiumPatrol;

use App\Enums\FreelancerSubscriptionTier;
use App\Enums\PremiumPatrolFlagStatus;
use App\Enums\PremiumPatrolFlagType;
use App\Enums\PremiumPatrolSubjectType;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\PremiumPatrolFlag;
use App\Models\PremiumPatrolWatchlist;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\QuestOffer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PremiumPatrolAnomalyService
{
    /** @var array<int, array{median: int, sample: int}>|null */
    private ?array $categoryBands = null;

    public function scanAll(): int
    {
        $created = 0;
        $created += $this->scanPremiumUsers();
        $created += $this->scanBoostedQuests();
        $created += $this->autoResolveEligible();

        return $created;
    }

    public function scanAfterPremiumPayment(FreelancerSubscriptionPayment $payment): void
    {
        $user = $payment->user;
        if (! $user) {
            return;
        }

        $this->flagNewAccountPremium($user, $payment);
        $this->flagBulkPremiumFromIp($payment);
        $this->flagWatchlistPremium($user);
    }

    public function scanAfterBoostActivated(QuestBoost $boost): void
    {
        $boost->loadMissing(['quest', 'client']);
        $this->flagBoostPatterns($boost);
    }

    private function scanPremiumUsers(): int
    {
        $created = 0;
        $subs = FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->with('user')
            ->get();

        foreach ($subs as $sub) {
            $user = $sub->user;
            if (! $user) {
                continue;
            }
            $created += $this->flagInactivePremium($user) ? 1 : 0;
            $created += $this->flagDisputeFraud($user) ? 1 : 0;
        }

        return $created;
    }

    private function scanBoostedQuests(): int
    {
        $created = 0;
        QuestBoost::query()->activeNow()->with(['quest', 'client'])->chunk(50, function ($boosts) use (&$created): void {
            foreach ($boosts as $boost) {
                $created += $this->flagBoostPatterns($boost) ? 1 : 0;
            }
        });

        return $created;
    }

    private function flagNewAccountPremium(User $user, FreelancerSubscriptionPayment $payment): bool
    {
        $days = (int) config('premium_patrol.new_account_days', 7);
        if ($user->created_at->diffInDays($payment->paid_at ?? now()) >= $days) {
            return false;
        }

        return $this->upsertFlag(
            PremiumPatrolSubjectType::PremiumUser,
            $user->id,
            PremiumPatrolFlagType::NewAccountPremium,
            "premium:new_account:{$user->id}",
            ['account_age_days' => $user->created_at->diffInDays($payment->paid_at ?? now()), 'payment_id' => $payment->id],
        );
    }

    private function flagBulkPremiumFromIp(FreelancerSubscriptionPayment $payment): bool
    {
        $ip = data_get($payment->meta, 'ip') ?? data_get($payment->meta, 'gateway.ip_address');
        if (! $ip) {
            return false;
        }

        $hours = (int) config('premium_patrol.bulk_premium_ip_hours', 24);
        $threshold = (int) config('premium_patrol.bulk_premium_ip_threshold', 5);
        $since = now()->subHours($hours);

        $count = FreelancerSubscriptionPayment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $since)
            ->where('meta->ip', $ip)
            ->count();

        if ($count < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            PremiumPatrolSubjectType::Aggregate,
            null,
            PremiumPatrolFlagType::BulkPremiumFraud,
            "premium:bulk_ip:{$ip}:{$hours}h",
            ['ip' => $ip, 'count' => $count, 'window_hours' => $hours],
            'high',
        );
    }

    private function flagInactivePremium(User $user): bool
    {
        $days = (int) config('premium_patrol.inactive_premium_days', 7);
        $sub = FreelancerSubscription::query()->where('user_id', $user->id)->first();
        if (! $sub?->isProActive()) {
            return false;
        }

        $recentProposals = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->exists();

        if ($recentProposals) {
            return false;
        }

        return $this->upsertFlag(
            PremiumPatrolSubjectType::PremiumUser,
            $user->id,
            PremiumPatrolFlagType::InactivePremium,
            "premium:inactive:{$user->id}",
            ['days' => $days],
            'low',
            autoResolveDays: (int) config('premium_patrol.auto_resolve_low_risk_days', 7),
        );
    }

    private function flagDisputeFraud(User $user): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('disputes')) {
            return false;
        }

        $recentPayment = FreelancerSubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->first();

        if (! $recentPayment?->paid_at) {
            return false;
        }

        $dispute = DB::table('disputes')
            ->where('raised_by_user_id', $user->id)
            ->where('created_at', '>=', $recentPayment->paid_at)
            ->where('created_at', '<=', $recentPayment->paid_at->copy()->addHours(48))
            ->exists();

        if (! $dispute) {
            return false;
        }

        return $this->upsertFlag(
            PremiumPatrolSubjectType::PremiumUser,
            $user->id,
            PremiumPatrolFlagType::DisputeFraud,
            "premium:dispute:{$user->id}:{$recentPayment->id}",
            ['payment_id' => $recentPayment->id],
            'high',
        );
    }

    private function flagWatchlistPremium(User $user): bool
    {
        $onWatchlist = PremiumPatrolWatchlist::query()
            ->where('watchlist_type', 'premium')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->exists();

        if (! $onWatchlist) {
            return false;
        }

        return $this->upsertFlag(
            PremiumPatrolSubjectType::PremiumUser,
            $user->id,
            PremiumPatrolFlagType::WatchlistPremium,
            "premium:watchlist:{$user->id}",
            [],
            'medium',
        );
    }

    private function flagBoostPatterns(QuestBoost $boost): bool
    {
        $created = false;
        $quest = $boost->quest;
        $client = $boost->client ?? $quest?->client;

        if ($client && $client->created_at->diffInDays($boost->granted_at ?? now()) < (int) config('premium_patrol.new_account_days', 7)) {
            $created = $this->upsertFlag(
                PremiumPatrolSubjectType::BoostedQuest,
                $boost->id,
                PremiumPatrolFlagType::NewAccountBoost,
                "boost:new_account:{$boost->id}",
                ['client_id' => $client->id, 'account_age_days' => $client->created_at->diffInDays($boost->granted_at ?? now())],
            ) || $created;
        }

        if ($quest && $quest->budget_amount_minor > 0) {
            $deviation = $this->budgetDeviationPercent($quest);
            if ($deviation !== null) {
                $threshold = (int) config('premium_patrol.budget_deviation_percent', 50);
                if ($deviation > $threshold) {
                    $created = $this->upsertFlag(
                        PremiumPatrolSubjectType::BoostedQuest,
                        $boost->id,
                        PremiumPatrolFlagType::SuspiciousBudget,
                        "boost:suspicious_budget:{$boost->id}",
                        ['deviation_percent' => $deviation, 'budget_minor' => $quest->budget_amount_minor],
                        $deviation >= 80 ? 'high' : 'medium',
                    ) || $created;

                    $created = $this->upsertFlag(
                        PremiumPatrolSubjectType::BoostedQuest,
                        $boost->id,
                        PremiumPatrolFlagType::ExtremePremium,
                        "boost:extreme_premium:{$boost->id}",
                        ['deviation_percent' => $deviation],
                        'medium',
                    ) || $created;
                } elseif ($deviation < -$threshold) {
                    $created = $this->upsertFlag(
                        PremiumPatrolSubjectType::BoostedQuest,
                        $boost->id,
                        PremiumPatrolFlagType::BelowMarketValue,
                        "boost:below_market:{$boost->id}",
                        ['deviation_percent' => $deviation],
                        'medium',
                    ) || $created;
                }
            }

            $boostPct = ($boost->planned_cost_minor / max(1, $quest->budget_amount_minor)) * 100;
            if ($boostPct > (int) config('premium_patrol.excessive_boost_cost_percent', 10)) {
                $created = $this->upsertFlag(
                    PremiumPatrolSubjectType::BoostedQuest,
                    $boost->id,
                    PremiumPatrolFlagType::ExcessiveBoostCost,
                    "boost:excessive_cost:{$boost->id}",
                    ['boost_cost_percent' => round($boostPct, 1)],
                    'medium',
                ) || $created;
            }
        }

        if ($client) {
            $hours = (int) config('premium_patrol.boost_spam_hours', 48);
            $threshold = (int) config('premium_patrol.boost_spam_threshold', 3);
            $count = QuestBoost::query()
                ->where('client_id', $client->id)
                ->where('granted_at', '>=', now()->subHours($hours))
                ->count();

            if ($count >= $threshold) {
                $created = $this->upsertFlag(
                    PremiumPatrolSubjectType::BoostedQuest,
                    $boost->id,
                    PremiumPatrolFlagType::BoostSpam,
                    "boost:spam:{$client->id}:{$hours}h",
                    ['client_id' => $client->id, 'count' => $count],
                    'high',
                ) || $created;
            }
        }

        $ineffectiveHours = (int) config('premium_patrol.ineffective_boost_hours', 24);
        if ($boost->starts_at && $boost->starts_at->lte(now()->subHours($ineffectiveHours))) {
            $proposals = QuestOffer::query()->where('quest_id', $boost->quest_id)->where('created_at', '>=', $boost->starts_at)->count();
            if ($proposals === 0) {
                $created = $this->upsertFlag(
                    PremiumPatrolSubjectType::BoostedQuest,
                    $boost->id,
                    PremiumPatrolFlagType::IneffectiveBoost,
                    "boost:ineffective:{$boost->id}",
                    ['hours' => $ineffectiveHours],
                    'low',
                    autoResolveDays: (int) config('premium_patrol.auto_resolve_low_risk_days', 7),
                ) || $created;
            }
        }

        return $created;
    }

    private function budgetDeviationPercent(Quest $quest): ?float
    {
        $bands = $this->categoryMarketBands();
        $categoryId = (int) ($quest->quest_category_id ?? 0);
        $median = $bands[$categoryId]['median'] ?? 0;
        if ($median <= 0 || ! $quest->budget_amount_minor) {
            return null;
        }

        return round((($quest->budget_amount_minor - $median) / $median) * 100, 1);
    }

    /**
     * @return array<int, array{median: int, sample: int}>
     */
    private function categoryMarketBands(): array
    {
        if ($this->categoryBands !== null) {
            return $this->categoryBands;
        }

        $days = (int) config('premium_patrol.market_rate_lookback_days', 90);
        $since = now()->subDays($days);

        $amounts = Quest::query()
            ->whereNotNull('quest_category_id')
            ->where('created_at', '>=', $since)
            ->where('budget_amount_minor', '>', 0)
            ->get(['quest_category_id', 'budget_amount_minor'])
            ->groupBy('quest_category_id');

        $this->categoryBands = $amounts->map(function ($group) {
            $values = $group->pluck('budget_amount_minor')->sort()->values();
            $count = $values->count();
            if ($count === 0) {
                return ['median' => 0, 'sample' => 0];
            }
            $mid = (int) floor($count / 2);

            return ['median' => (int) $values[$mid], 'sample' => $count];
        })->all();

        return $this->categoryBands;
    }

    private function autoResolveEligible(): int
    {
        $resolved = 0;
        PremiumPatrolFlag::query()
            ->where('status', PremiumPatrolFlagStatus::Open->value)
            ->whereNotNull('auto_resolve_at')
            ->where('auto_resolve_at', '<=', now())
            ->each(function (PremiumPatrolFlag $flag) use (&$resolved): void {
                $flag->forceFill([
                    'status' => PremiumPatrolFlagStatus::AutoResolved->value,
                    'resolved_at' => now(),
                ])->save();
                $resolved++;
            });

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function upsertFlag(
        PremiumPatrolSubjectType $subjectType,
        ?int $subjectId,
        PremiumPatrolFlagType $flagType,
        string $fingerprint,
        array $meta,
        ?string $severity = null,
        ?int $autoResolveDays = null,
    ): bool {
        $existing = PremiumPatrolFlag::query()->where('fingerprint', $fingerprint)->first();
        if ($existing && $existing->status !== PremiumPatrolFlagStatus::Open->value) {
            return false;
        }

        if ($existing) {
            return false;
        }

        PremiumPatrolFlag::query()->create([
            'subject_type' => $subjectType->value,
            'subject_id' => $subjectId,
            'flag_type' => $flagType->value,
            'severity' => $severity ?? $flagType->defaultSeverity(),
            'status' => PremiumPatrolFlagStatus::Open->value,
            'fingerprint' => $fingerprint,
            'meta' => $meta,
            'detected_at' => now(),
            'auto_resolve_at' => $autoResolveDays ? now()->addDays($autoResolveDays) : null,
        ]);

        return true;
    }

    public function resolveInactivePremiumIfActive(User $user): void
    {
        PremiumPatrolFlag::query()
            ->where('subject_type', PremiumPatrolSubjectType::PremiumUser->value)
            ->where('subject_id', $user->id)
            ->where('flag_type', PremiumPatrolFlagType::InactivePremium->value)
            ->where('status', PremiumPatrolFlagStatus::Open->value)
            ->update([
                'status' => PremiumPatrolFlagStatus::AutoResolved->value,
                'resolved_at' => now(),
            ]);
    }
}
