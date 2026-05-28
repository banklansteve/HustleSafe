<?php

namespace App\Services\Payments;

use App\Enums\PaymentAnomalyType;
use App\Models\PaymentEscrow;
use App\Models\PaymentReviewFlag;
use App\Models\Quest;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentMonitoringAnomalyEngine
{
    /** @var array<int, array{median: int, p25: int, p75: int, min: int, max: int, sample: int}>|null */
    private ?array $categoryBands = null;

    /**
     * Normalised payment-risk contribution (0–100) for composite trust scoring.
     */
    public function riskContributionForUser(int $userId): float
    {
        $items = $this->detectAll()->filter(function (array $row) use ($userId): bool {
            $clientId = (int) ($row['client']['id'] ?? 0);
            $freelancerId = (int) ($row['freelancer']['id'] ?? 0);
            $metaFreelancer = (int) ($row['metadata']['freelancer_id'] ?? 0);

            return $clientId === $userId
                || $freelancerId === $userId
                || $metaFreelancer === $userId;
        });

        if ($items->isEmpty()) {
            return 0.0;
        }

        $weights = ['high' => 35, 'medium' => 20, 'low' => 8];

        return (float) min(100, $items->sum(fn (array $row) => $weights[$row['severity']] ?? 10));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectAll(): Collection
    {
        return collect()
            ->merge($this->detectEscrowOverFunding())
            ->merge($this->detectSmurfingPatterns())
            ->merge($this->detectPayoutVelocitySpikes())
            ->merge($this->detectRapidEscrowReleases())
            ->merge($this->detectContractMarketRateOutliers());
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>, filters: array<string, mixed>}
     */
    public function listing(Request $request): array
    {
        $severity = (string) $request->query('severity', '');
        $type = (string) $request->query('anomaly_type', '');
        $sort = (string) $request->query('sort', 'severity_desc');
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $page = max(1, (int) $request->query('page', 1));

        $items = $this->detectAll();

        if ($severity !== '' && in_array($severity, ['low', 'medium', 'high'], true)) {
            $items = $items->filter(fn (array $row) => $row['severity'] === $severity);
        }

        if ($type !== '') {
            $items = $items->filter(fn (array $row) => $row['anomaly_type'] === $type);
        }

        $items = $this->sortAnomalies($items, $sort)->values();

        $fingerprints = $items->pluck('anomaly_fingerprint')->filter()->all();
        $flagged = PaymentReviewFlag::query()
            ->whereIn('anomaly_fingerprint', $fingerprints)
            ->where('resolution_status', 'pending')
            ->pluck('id', 'anomaly_fingerprint');

        $items = $items->map(function (array $row) use ($flagged) {
            $row['staff_flag_id'] = $flagged[$row['anomaly_fingerprint']] ?? null;
            $row['has_pending_flag'] = isset($flagged[$row['anomaly_fingerprint']]);

            return $row;
        });

        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'items' => $slice->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
            'filters' => [
                'severity' => $severity,
                'anomaly_type' => $type,
                'sort' => $sort,
            ],
            'anomaly_types' => collect(PaymentAnomalyType::cases())
                ->map(fn (PaymentAnomalyType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function detectEscrowOverFunding(): Collection
    {
        $threshold = (int) config('payment_monitoring.over_funding_percent', 20);

        return PaymentEscrow::query()
            ->with(['quest:id,title,reference_code,quest_category_id', 'offer:id,quoted_amount_minor', 'client:id,name,email', 'freelancer:id,name,email'])
            ->whereNotNull('funded_at')
            ->where('funded_at', '>=', now()->subDays(120))
            ->get()
            ->filter(function (PaymentEscrow $escrow) use ($threshold) {
                $agreed = (int) ($escrow->offer?->quoted_amount_minor ?? $escrow->quest?->budget_amount_minor ?? 0);
                if ($agreed <= 0) {
                    return false;
                }

                return (int) $escrow->amount_minor > (int) floor($agreed * (1 + ($threshold / 100)));
            })
            ->map(function (PaymentEscrow $escrow) use ($threshold) {
                $agreed = (int) ($escrow->offer?->quoted_amount_minor ?? $escrow->quest?->budget_amount_minor ?? 0);
                $funded = (int) $escrow->amount_minor;
                $overPct = $agreed > 0 ? round((($funded - $agreed) / $agreed) * 100, 1) : 0;

                return $this->anomalyRow(
                    PaymentAnomalyType::EscrowOverFunding,
                    $overPct >= 50 ? 'high' : ($overPct >= 30 ? 'medium' : 'low'),
                    "overfund:{$escrow->id}",
                    $escrow,
                    [
                        'agreed_amount_minor' => $agreed,
                        'funded_amount_minor' => $funded,
                        'over_percent' => $overPct,
                        'threshold_percent' => $threshold,
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function detectSmurfingPatterns(): Collection
    {
        $days = (int) config('payment_monitoring.smurfing_window_days', 7);
        $minDeposits = (int) config('payment_monitoring.smurfing_min_deposits', 3);
        $minClients = (int) config('payment_monitoring.smurfing_min_clients', 3);
        $maxMinor = (int) config('payment_monitoring.smurfing_max_amount_minor', 5_000_000);
        $since = now()->subDays($days);

        $rows = PaymentEscrow::query()
            ->whereNotNull('funded_at')
            ->where('funded_at', '>=', $since)
            ->where('amount_minor', '<=', $maxMinor)
            ->get(['id', 'freelancer_id', 'client_id', 'amount_minor', 'funded_at', 'reference', 'quest_id']);

        return $rows
            ->groupBy('freelancer_id')
            ->map(function (Collection $group) use ($minDeposits, $minClients) {
                $clients = $group->pluck('client_id')->unique();

                return $clients->count() >= $minClients && $group->count() >= $minDeposits
                    ? ['freelancer_id' => $group->first()->freelancer_id, 'deposits' => $group]
                    : null;
            })
            ->filter()
            ->map(function (array $bundle) use ($days, $minDeposits, $minClients) {
                /** @var Collection<int, PaymentEscrow> $deposits */
                $deposits = $bundle['deposits'];
                $first = $deposits->sortBy('funded_at')->first();
                $totalMinor = (int) $deposits->sum('amount_minor');

                return $this->anomalyRow(
                    PaymentAnomalyType::SmurfingPattern,
                    $deposits->count() >= 5 ? 'high' : 'medium',
                    'smurf:'.$bundle['freelancer_id'].':'.$deposits->min('id'),
                    $first,
                    [
                        'freelancer_id' => $bundle['freelancer_id'],
                        'deposit_count' => $deposits->count(),
                        'distinct_clients' => $deposits->pluck('client_id')->unique()->count(),
                        'window_days' => $days,
                        'min_deposits' => $minDeposits,
                        'min_clients' => $minClients,
                        'total_amount_minor' => $totalMinor,
                        'deposits' => $deposits->map(fn (PaymentEscrow $e) => [
                            'escrow_id' => $e->id,
                            'reference' => $e->reference,
                            'client_id' => $e->client_id,
                            'amount_minor' => (int) $e->amount_minor,
                            'funded_at' => $e->funded_at?->toIso8601String(),
                        ])->values()->all(),
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function detectPayoutVelocitySpikes(): Collection
    {
        $multiplier = (float) config('payment_monitoring.payout_velocity_multiplier', 2.5);
        $rollingDays = (int) config('payment_monitoring.payout_velocity_rolling_days', 30);
        $today = now()->toDateString();

        $todayCounts = WalletTransaction::query()
            ->selectRaw('user_id, COUNT(*) as payout_count')
            ->where('type', 'escrow_release')
            ->where('direction', 'credit')
            ->where('status', 'completed')
            ->whereDate('occurred_at', $today)
            ->groupBy('user_id')
            ->pluck('payout_count', 'user_id');

        if ($todayCounts->isEmpty()) {
            return collect();
        }

        $averages = WalletTransaction::query()
            ->selectRaw('user_id, COUNT(*) / ? as avg_daily', [$rollingDays])
            ->where('type', 'escrow_release')
            ->where('direction', 'credit')
            ->where('status', 'completed')
            ->where('occurred_at', '>=', now()->subDays($rollingDays))
            ->whereIn('user_id', $todayCounts->keys())
            ->groupBy('user_id')
            ->pluck('avg_daily', 'user_id');

        $anomalies = collect();

        foreach ($todayCounts as $userId => $count) {
            $avg = (float) ($averages[$userId] ?? 0);
            if ($avg <= 0 || $count < 2) {
                continue;
            }

            if ($count <= ($avg * $multiplier)) {
                continue;
            }

            $latestTx = WalletTransaction::query()
                ->where('user_id', $userId)
                ->where('type', 'escrow_release')
                ->where('direction', 'credit')
                ->latest('occurred_at')
                ->first();

            $anomalies->push($this->anomalyRow(
                PaymentAnomalyType::PayoutVelocitySpike,
                $count >= $avg * 4 ? 'high' : 'medium',
                "payout_velocity:{$userId}:{$today}",
                null,
                [
                    'freelancer_id' => (int) $userId,
                    'today_payout_count' => (int) $count,
                    'rolling_avg_daily' => round($avg, 2),
                    'multiplier_threshold' => $multiplier,
                    'rolling_days' => $rollingDays,
                ],
                walletTransaction: $latestTx,
            ));
        }

        return $anomalies;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function detectRapidEscrowReleases(): Collection
    {
        $hours = (int) config('payment_monitoring.rapid_release_hours', 12);

        return PaymentEscrow::query()
            ->with(['quest:id,title,reference_code,escrow_funded_at,scheduled_start_date,accepted_quest_offer_id', 'offer:id,accepted_at'])
            ->whereNotNull('funded_at')
            ->whereNotNull('released_at')
            ->where('released_at', '>=', now()->subDays(90))
            ->get()
            ->filter(function (PaymentEscrow $escrow) use ($hours) {
                $start = $escrow->quest?->escrow_funded_at ?? $escrow->funded_at;
                if ($start === null || $escrow->released_at === null) {
                    return false;
                }

                return $start->diffInHours($escrow->released_at) <= $hours;
            })
            ->map(function (PaymentEscrow $escrow) use ($hours) {
                $start = $escrow->quest?->escrow_funded_at ?? $escrow->funded_at;
                $elapsed = $start?->diffInHours($escrow->released_at) ?? 0;

                return $this->anomalyRow(
                    PaymentAnomalyType::RapidEscrowRelease,
                    $elapsed <= 3 ? 'high' : 'medium',
                    "rapid_release:{$escrow->id}",
                    $escrow,
                    [
                        'hours_elapsed' => $elapsed,
                        'threshold_hours' => $hours,
                        'contract_started_at' => $start?->toIso8601String(),
                        'released_at' => $escrow->released_at?->toIso8601String(),
                    ],
                );
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function detectContractMarketRateOutliers(): Collection
    {
        $highPct = (int) config('payment_monitoring.market_rate_high_deviation_percent', 40);
        $lowPct = (int) config('payment_monitoring.market_rate_low_deviation_percent', 30);
        $bands = $this->categoryMarketBands();

        return PaymentEscrow::query()
            ->with(['quest:id,title,reference_code,quest_category_id,budget_amount_minor', 'offer:id,quoted_amount_minor', 'client:id,name', 'freelancer:id,name'])
            ->whereNotNull('funded_at')
            ->where('funded_at', '>=', now()->subDays(60))
            ->get()
            ->map(function (PaymentEscrow $escrow) use ($bands, $highPct, $lowPct) {
                $categoryId = (int) ($escrow->quest?->quest_category_id ?? 0);
                $band = $bands[$categoryId] ?? null;
                if ($band === null || $band['median'] <= 0) {
                    return null;
                }

                $contract = (int) ($escrow->offer?->quoted_amount_minor ?? $escrow->quest?->budget_amount_minor ?? 0);
                if ($contract <= 0) {
                    return null;
                }

                $median = (int) $band['median'];
                $deviationPct = round((($contract - $median) / $median) * 100, 1);
                $direction = null;
                $severity = null;

                if ($deviationPct > $highPct) {
                    $direction = 'above';
                    $severity = $deviationPct >= 80 ? 'high' : 'medium';
                } elseif ($deviationPct < -$lowPct) {
                    $direction = 'below';
                    $severity = $deviationPct <= -50 ? 'high' : 'medium';
                }

                if ($direction === null) {
                    return null;
                }

                return $this->anomalyRow(
                    PaymentAnomalyType::ContractMarketRateOutlier,
                    $severity,
                    "market:{$escrow->id}:{$direction}",
                    $escrow,
                    [
                        'contract_amount_minor' => $contract,
                        'category_median_minor' => $median,
                        'deviation_percent' => $deviationPct,
                        'direction' => $direction,
                        'category_id' => $categoryId,
                        'high_threshold_percent' => $highPct,
                        'low_threshold_percent' => $lowPct,
                    ],
                    marketBand: $band,
                );
            })
            ->filter();
    }

    /**
     * @return array<int, array{median: int, p25: int, p75: int, min: int, max: int, sample: int}>
     */
    private function categoryMarketBands(): array
    {
        if ($this->categoryBands !== null) {
            return $this->categoryBands;
        }

        $days = (int) config('payment_monitoring.market_rate_lookback_days', 90);
        $since = now()->subDays($days);

        $amounts = Quest::query()
            ->whereNotNull('quest_category_id')
            ->where('created_at', '>=', $since)
            ->whereNotNull('budget_amount_minor')
            ->where('budget_amount_minor', '>', 0)
            ->get(['quest_category_id', 'budget_amount_minor'])
            ->groupBy('quest_category_id');

        $bands = [];
        foreach ($amounts as $categoryId => $quests) {
            $values = $quests->pluck('budget_amount_minor')->map(fn ($v) => (int) $v)->sort()->values();
            $count = $values->count();
            if ($count < 5) {
                continue;
            }

            $bands[(int) $categoryId] = [
                'median' => (int) $values->median(),
                'p25' => (int) $values->get((int) floor($count * 0.25)),
                'p75' => (int) $values->get((int) floor($count * 0.75)),
                'min' => (int) $values->min(),
                'max' => (int) $values->max(),
                'sample' => $count,
            ];
        }

        return $this->categoryBands = $bands;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{median: int, p25: int, p75: int, min: int, max: int, sample: int}|null  $marketBand
     */
    private function anomalyRow(
        PaymentAnomalyType $type,
        string $severity,
        string $fingerprint,
        ?PaymentEscrow $escrow,
        array $metadata,
        ?WalletTransaction $walletTransaction = null,
        ?array $marketBand = null,
    ): array {
        $escrow?->loadMissing(['quest:id,title,reference_code', 'client:id,name,email', 'freelancer:id,name,email']);

        return [
            'id' => $fingerprint,
            'anomaly_fingerprint' => $fingerprint,
            'anomaly_type' => $type->value,
            'anomaly_label' => $type->label(),
            'severity' => $severity,
            'detected_at' => now()->toIso8601String(),
            'payment_escrow_id' => $escrow?->id,
            'quest_id' => $escrow?->quest_id ?? $metadata['quest_id'] ?? null,
            'wallet_transaction_id' => $walletTransaction?->id,
            'transaction_reference' => $escrow?->reference ?? $walletTransaction?->reference,
            'amount_minor' => (int) ($escrow?->amount_minor ?? $walletTransaction?->amount_minor ?? ($metadata['total_amount_minor'] ?? 0)),
            'quest_title' => $escrow?->quest?->title,
            'quest_reference' => $escrow?->quest?->reference_code,
            'client' => $escrow?->client ? ['id' => $escrow->client->id, 'name' => $escrow->client->name, 'email' => $escrow->client->email] : null,
            'freelancer' => $escrow?->freelancer ? ['id' => $escrow->freelancer->id, 'name' => $escrow->freelancer->name, 'email' => $escrow->freelancer->email] : null,
            'metadata' => $metadata,
            'market_band' => $marketBand,
            'has_pending_flag' => false,
            'staff_flag_id' => null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function sortAnomalies(Collection $items, string $sort): Collection
    {
        $severityWeight = ['high' => 3, 'medium' => 2, 'low' => 1];

        return match ($sort) {
            'date_asc' => $items->sortBy('detected_at'),
            'amount_desc' => $items->sortByDesc('amount_minor'),
            'amount_asc' => $items->sortBy('amount_minor'),
            'type' => $items->sortBy('anomaly_label'),
            default => $items->sort(function (array $a, array $b) use ($severityWeight) {
                $s = ($severityWeight[$b['severity']] ?? 0) <=> ($severityWeight[$a['severity']] ?? 0);
                if ($s !== 0) {
                    return $s;
                }

                return ($b['amount_minor'] ?? 0) <=> ($a['amount_minor'] ?? 0);
            }),
        };
    }
}
