<?php

namespace App\Services\Admin;

use App\Models\AdminFinancialLedgerEntry;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialControlCentreService
{
    public function summary(): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfDay = $now->copy()->startOfDay();

        $escrowBalance = $this->escrowQuery()->get()->sum(fn (Quest $quest) => $this->heldMinor($quest));
        $monthRevenue = $this->revenueMinor($startOfMonth, $now);
        $payoutsToday = (int) AdminFinancialLedgerEntry::query()
            ->where('type', 'payout')
            ->whereBetween('occurred_at', [$startOfDay, $now])
            ->sum('net_amount_minor');
        $frozen = $this->escrowQuery()
            ->where(fn (Builder $q) => $q->whereNotNull('escrow_frozen_at')->orWhere('dispute_opened', true)->orWhere('escrow_status', 'disputed'))
            ->get()
            ->sum(fn (Quest $quest) => $this->heldMinor($quest));

        return [
            'escrow_balance' => $this->money($escrowBalance),
            'escrow_balance_minor' => $escrowBalance,
            'month_revenue' => $this->money($monthRevenue),
            'month_revenue_minor' => $monthRevenue,
            'payouts_today' => $this->money($payoutsToday),
            'payouts_today_minor' => $payoutsToday,
            'frozen_funds' => $this->money($frozen),
            'frozen_funds_minor' => $frozen,
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    public function escrowPage(Request $request): array
    {
        $query = $this->escrowQuery()->with(['client:id,name,email', 'freelancer:id,name,email', 'questCategory:id,name']);
        $this->applyEscrowFilters($query, $request);

        $quests = $query->latest('escrow_funded_at')->paginate(min(50, max(10, $request->integer('per_page', 15))))->withQueryString();

        return [
            'tiles' => [
                ['label' => 'Total escrow held', 'value' => $this->money($this->escrowQuery()->get()->sum(fn (Quest $q) => $this->heldMinor($q)))],
                ['label' => 'Frozen due to disputes', 'value' => $this->money($this->escrowQuery()->where(fn (Builder $q) => $q->where('dispute_opened', true)->orWhere('escrow_status', 'disputed')->orWhereNotNull('escrow_frozen_at'))->get()->sum(fn (Quest $q) => $this->heldMinor($q)))],
                ['label' => 'Pending release', 'value' => $this->money($this->escrowQuery()->whereIn('status', ['pending_review', 'delivered'])->get()->sum(fn (Quest $q) => $this->heldMinor($q)))],
            ],
            'trend' => $this->escrowTrend(),
            'escrows' => $quests->through(fn (Quest $quest) => $this->escrowRow($quest)),
            'filters' => $request->only(['q', 'status', 'per_page']),
        ];
    }

    public function revenuePage(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();
        $monthRevenue = $this->revenueMinor($monthStart, $now);
        $lastMonthRevenue = $this->revenueMinor($lastMonthStart, $lastMonthEnd);
        $serviceRevenue = $this->serviceFeeMinor($monthStart, $now);
        $featuredRevenue = $this->featuredRevenueMinor($monthStart, $now);
        $disputeRevenue = $this->disputeFeeMinor($monthStart, $now);
        $projected = $now->day > 0 ? (int) round(($monthRevenue / max(1, $now->day)) * $now->daysInMonth) : $monthRevenue;

        return [
            'kpis' => [
                ['label' => 'Total Revenue This Month', 'value' => $this->money($monthRevenue), 'change' => $this->change($monthRevenue, $lastMonthRevenue), 'sparkline' => $this->dailyRevenueSeries('total')],
                ['label' => 'Total Revenue Last Month', 'value' => $this->money($lastMonthRevenue), 'change' => null, 'sparkline' => $this->dailyRevenueSeries('total', now()->subMonth())],
                ['label' => 'Service Fee Revenue', 'value' => $this->money($serviceRevenue), 'change' => null, 'sparkline' => $this->dailyRevenueSeries('service_fee')],
                ['label' => 'Featured Listing Revenue', 'value' => $this->money($featuredRevenue), 'change' => null, 'sparkline' => $this->dailyRevenueSeries('featured_listing')],
                ['label' => 'Dispute Fee Revenue', 'value' => $this->money($disputeRevenue), 'change' => null, 'sparkline' => $this->dailyRevenueSeries('dispute_fee')],
                ['label' => 'Projected Month-End Revenue', 'value' => $this->money($projected), 'change' => null, 'sparkline' => $this->dailyRevenueSeries('total')],
            ],
            'monthly_stack' => $this->monthlyRevenueStack(),
            'category_rows' => $this->revenueByCategory(),
            'state_rows' => $this->revenueByState(),
            'mrr' => ['label' => 'Monthly Recurring Revenue', 'value' => '₦0.00', 'status' => 'Coming soon'],
        ];
    }

    public function ledgerPage(Request $request): array
    {
        $query = AdminFinancialLedgerEntry::query()
            ->with(['quest:id,title,reference_code', 'client:id,name,email', 'freelancer:id,name,email', 'admin:id,name,email']);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhereHas('quest', fn (Builder $quest) => $quest->where('title', 'like', '%'.$search.'%')->orWhere('reference_code', 'like', '%'.$search.'%'))
                    ->orWhereHas('client', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))
                    ->orWhereHas('freelancer', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
            });
        }

        foreach (['type', 'source'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->input('to'));
        }
        if ($request->filled('amount_min')) {
            $query->where('gross_amount_minor', '>=', (int) round(((float) $request->input('amount_min')) * 100));
        }
        if ($request->filled('amount_max')) {
            $query->where('gross_amount_minor', '<=', (int) round(((float) $request->input('amount_max')) * 100));
        }

        return [
            'ledger' => $query->latest('occurred_at')
                ->paginate(min(100, max(20, $request->integer('per_page', 30))))
                ->withQueryString()
                ->through(fn (AdminFinancialLedgerEntry $entry) => $this->ledgerRow($entry)),
            'filters' => $request->only(['q', 'type', 'source', 'from', 'to', 'amount_min', 'amount_max', 'per_page']),
            'type_options' => ['escrow_funding', 'milestone_release', 'platform_fee', 'payout', 'refund', 'dispute_freeze', 'dispute_release', 'admin_adjustment', 'featured_listing_payment'],
        ];
    }

    public function escrowLedger(Quest $quest): array
    {
        $quest->loadMissing(['client:id,name,email', 'freelancer:id,name,email', 'acceptedOffer']);
        $entries = collect();
        $balance = 0;

        if ($quest->escrow_funded_at !== null || in_array($quest->escrow_status, ['funded', 'partially_released', 'released', 'disputed', 'refunded', 'frozen'], true)) {
            $balance += $this->escrowAmount($quest);
            $entries->push([
                'reference' => $quest->reference_code.'-FUND',
                'type' => 'escrow_funding',
                'description' => 'Initial funding into escrow',
                'amount' => $this->money($this->escrowAmount($quest)),
                'amount_minor' => $this->escrowAmount($quest),
                'balance' => $this->money($balance),
                'occurred_at' => ($quest->escrow_funded_at ?? $quest->updated_at)?->toIso8601String(),
            ]);
        }

        foreach ($quest->adminFinancialLedgerEntries()->with('admin:id,name,email')->orderBy('occurred_at')->get() as $entry) {
            $balance = (int) $entry->balance_after_minor;
            $entries->push([
                'reference' => $entry->reference,
                'type' => $entry->type,
                'description' => $entry->description,
                'amount' => $this->money($entry->net_amount_minor),
                'amount_minor' => $entry->net_amount_minor,
                'balance' => $this->money($balance),
                'occurred_at' => $entry->occurred_at?->toIso8601String(),
                'admin' => $entry->admin?->name,
                'reason' => $entry->admin_reason,
            ]);
        }

        return [
            'escrow' => $this->escrowRow($quest),
            'entries' => $entries->sortBy('occurred_at')->values(),
            'controls' => [
                'held_minor' => $this->heldMinor($quest),
                'held' => $this->money($this->heldMinor($quest)),
                'fee_percent' => (float) config('quests.platform_fee_percent_display', 5),
            ],
        ];
    }

    public function applyEscrowAction(Quest $quest, User $admin, array $data): AdminFinancialLedgerEntry
    {
        return DB::transaction(function () use ($quest, $admin, $data): AdminFinancialLedgerEntry {
            $quest->refresh();
            $held = $this->heldMinor($quest);
            $type = (string) $data['action'];
            $amount = isset($data['amount']) ? (int) round(((float) $data['amount']) * 100) : $held;
            $reason = (string) $data['reason'];
            $feePercent = (float) config('quests.platform_fee_percent_display', 5);
            $fee = in_array($type, ['manual_release', 'partial_refund'], true) ? (int) round($amount * ($feePercent / 100)) : 0;

            if (in_array($type, ['manual_release', 'full_refund', 'partial_refund'], true) && ($amount <= 0 || $amount > $held)) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero and not exceed the held escrow balance.']);
            }

            $entryType = match ($type) {
                'manual_release' => 'milestone_release',
                'manual_hold' => 'admin_adjustment',
                'freeze' => 'dispute_freeze',
                'unfreeze' => 'dispute_release',
                'full_refund', 'partial_refund' => 'refund',
                default => 'admin_adjustment',
            };
            $direction = match ($type) {
                'manual_release' => 'out_to_freelancer',
                'full_refund', 'partial_refund' => 'out_to_client',
                'freeze' => 'frozen',
                'unfreeze' => 'unfrozen',
                default => 'admin_adjustment',
            };

            if ($type === 'manual_hold') {
                $quest->forceFill([
                    'escrow_status' => 'held',
                    'escrow_held_at' => now(),
                    'escrow_hold_reason' => $reason,
                    'escrow_hold_expected_resolution_at' => $data['expected_resolution_at'] ?? null,
                ])->save();
                $amount = 0;
            } elseif ($type === 'freeze') {
                $quest->forceFill(['escrow_status' => 'frozen', 'escrow_frozen_at' => now(), 'escrow_freeze_reason' => $reason])->save();
                $amount = 0;
            } elseif ($type === 'unfreeze') {
                if ($admin->role?->slug !== 'super_admin') {
                    abort(403, 'Only a super admin can unfreeze escrow.');
                }
                $quest->forceFill(['escrow_status' => 'funded', 'escrow_frozen_at' => null, 'escrow_freeze_reason' => null])->save();
                $amount = 0;
            } elseif ($type === 'manual_release') {
                $quest->forceFill([
                    'paid_out_minor' => (int) $quest->paid_out_minor + $amount,
                    'escrow_status' => ((int) $quest->paid_out_minor + $amount) >= $this->escrowAmount($quest) ? 'released' : 'partially_released',
                ])->save();
            } elseif ($type === 'full_refund') {
                $quest->forceFill([
                    'refunded_minor' => (int) $quest->refunded_minor + $held,
                    'escrow_status' => 'refunded',
                ])->save();
                $amount = $held;
            } elseif ($type === 'partial_refund') {
                $freelancerAmount = isset($data['freelancer_amount']) ? (int) round(((float) $data['freelancer_amount']) * 100) : max(0, $held - $amount);
                if ($amount + $freelancerAmount !== $held) {
                    throw ValidationException::withMessages(['amount' => 'Client refund and freelancer release must add up to the held balance.']);
                }
                $quest->forceFill([
                    'refunded_minor' => (int) $quest->refunded_minor + $amount,
                    'paid_out_minor' => (int) $quest->paid_out_minor + $freelancerAmount,
                    'escrow_status' => 'released',
                ])->save();
            }

            return AdminFinancialLedgerEntry::query()->create([
                'quest_id' => $quest->id,
                'quest_offer_id' => $quest->accepted_quest_offer_id,
                'client_id' => $quest->client_id,
                'freelancer_id' => $quest->freelancer_id,
                'admin_user_id' => $admin->id,
                'type' => $entryType,
                'direction' => $direction,
                'source' => 'admin',
                'description' => $this->actionDescription($type, $data),
                'gross_amount_minor' => $amount,
                'fee_amount_minor' => $fee,
                'net_amount_minor' => $type === 'manual_release' ? max(0, $amount - $fee) : -$amount,
                'balance_after_minor' => $this->heldMinor($quest->fresh()),
                'admin_reason' => $reason,
                'meta' => ['milestone' => $data['milestone'] ?? null, 'raw_action' => $type],
                'occurred_at' => now(),
            ]);
        });
    }

    private function escrowQuery(): Builder
    {
        return Quest::query()->whereIn('escrow_status', ['funded', 'awaiting_funding', 'partially_released', 'released', 'fully_released', 'frozen', 'held', 'disputed', 'refunded']);
    }

    private function applyEscrowFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('reference_code', 'like', '%'.$search.'%')
                    ->orWhereHas('client', fn (Builder $u) => $u->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('freelancer', fn (Builder $u) => $u->where('name', 'like', '%'.$search.'%'));
            });
        }
        if ($request->filled('status')) {
            $query->where('escrow_status', $request->input('status'));
        }
    }

    private function escrowRow(Quest $quest): array
    {
        $amount = $this->escrowAmount($quest);
        $released = (int) $quest->paid_out_minor;
        $refunded = (int) ($quest->refunded_minor ?? 0);

        return [
            'id' => $quest->id,
            'contract_id' => $quest->reference_code ?? ('Q-'.$quest->id),
            'title' => $quest->title,
            'client' => $quest->client?->name,
            'freelancer' => $quest->freelancer?->name,
            'amount' => $this->money($amount),
            'amount_minor' => $amount,
            'released' => $this->money($released),
            'released_minor' => $released,
            'held' => $this->money(max(0, $amount - $released - $refunded)),
            'held_minor' => max(0, $amount - $released - $refunded),
            'status' => $quest->escrow_status,
            'funded_at' => $quest->escrow_funded_at?->toIso8601String(),
        ];
    }

    private function ledgerRow(AdminFinancialLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'reference' => $entry->reference,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'type' => $entry->type,
            'quest' => $entry->quest?->title,
            'contract_id' => $entry->quest?->reference_code,
            'client' => $entry->client?->name,
            'freelancer' => $entry->freelancer?->name,
            'direction' => $entry->direction,
            'gross' => $this->money((int) $entry->gross_amount_minor),
            'fee' => $this->money((int) $entry->fee_amount_minor),
            'net' => $this->money((int) $entry->net_amount_minor),
            'source' => $entry->source,
            'admin' => $entry->admin?->name,
            'reason' => $entry->admin_reason,
            'paystack_reference' => $entry->paystack_reference,
        ];
    }

    private function escrowAmount(Quest $quest): int
    {
        return (int) ($quest->acceptedOffer?->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0);
    }

    private function heldMinor(Quest $quest): int
    {
        return max(0, $this->escrowAmount($quest) - (int) $quest->paid_out_minor - (int) ($quest->refunded_minor ?? 0));
    }

    private function serviceFeeMinor(Carbon $from, Carbon $to): int
    {
        return (int) round(((int) Quest::query()->whereBetween('completed_at', [$from, $to])->sum('paid_out_minor')) * ((float) config('quests.platform_fee_percent_display', 5) / 100));
    }

    private function featuredRevenueMinor(Carbon $from, Carbon $to): int
    {
        return (int) Quest::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('promotion_tier', ['standard', 'none', ''])
            ->count() * 250000;
    }

    private function disputeFeeMinor(Carbon $from, Carbon $to): int
    {
        return (int) DB::table('quest_disputes')->whereBetween('resolved_at', [$from, $to])->count() * 100000;
    }

    private function revenueMinor(Carbon $from, Carbon $to): int
    {
        return $this->serviceFeeMinor($from, $to) + $this->featuredRevenueMinor($from, $to) + $this->disputeFeeMinor($from, $to);
    }

    private function dailyRevenueSeries(string $source, ?Carbon $anchor = null): array
    {
        $anchor ??= now();
        $start = $anchor->copy()->subDays(29)->startOfDay();

        return collect(range(0, 29))->map(function (int $i) use ($start, $source): array {
            $day = $start->copy()->addDays($i);
            $value = match ($source) {
                'service_fee' => $this->serviceFeeMinor($day->copy()->startOfDay(), $day->copy()->endOfDay()),
                'featured_listing' => $this->featuredRevenueMinor($day->copy()->startOfDay(), $day->copy()->endOfDay()),
                'dispute_fee' => $this->disputeFeeMinor($day->copy()->startOfDay(), $day->copy()->endOfDay()),
                default => $this->revenueMinor($day->copy()->startOfDay(), $day->copy()->endOfDay()),
            };

            return ['label' => $day->format('M j'), 'value' => $value / 100];
        })->all();
    }

    private function monthlyRevenueStack(): array
    {
        return collect(range(11, 0))->map(function (int $monthsAgo): array {
            $month = now()->subMonths($monthsAgo);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();

            return [
                'label' => $month->format('M Y'),
                'service_fee' => $this->serviceFeeMinor($from, $to) / 100,
                'featured_listing' => $this->featuredRevenueMinor($from, $to) / 100,
                'dispute_fee' => $this->disputeFeeMinor($from, $to) / 100,
            ];
        })->values()->all();
    }

    private function escrowTrend(): array
    {
        return collect(range(89, 0))->map(function (int $daysAgo): array {
            $day = now()->subDays($daysAgo)->endOfDay();
            $value = Quest::query()
                ->whereNotNull('escrow_funded_at')
                ->where('escrow_funded_at', '<=', $day)
                ->get()
                ->sum(fn (Quest $q) => max(0, $this->escrowAmount($q) - (int) $q->paid_out_minor - (int) ($q->refunded_minor ?? 0)));

            return ['label' => $day->format('M j'), 'value' => round($value / 100, 2)];
        })->all();
    }

    private function revenueByCategory(): array
    {
        $from = now()->startOfMonth();
        $feePercent = (float) config('quests.platform_fee_percent_display', 5);

        return QuestCategory::query()
            ->withCount(['quests as contracts_completed' => fn (Builder $q) => $q->whereBetween('completed_at', [$from, now()])])
            ->withSum(['quests as gmv_minor' => fn (Builder $q) => $q->whereBetween('completed_at', [$from, now()])], 'paid_out_minor')
            ->get()
            ->map(fn (QuestCategory $category) => [
                'label' => $category->name,
                'contracts_completed' => (int) $category->contracts_completed,
                'gmv' => $this->money((int) $category->gmv_minor),
                'service_fee' => $this->money((int) round(((int) $category->gmv_minor) * ($feePercent / 100))),
                'fee_percent' => $feePercent.'%',
                'mom_change' => '—',
            ])
            ->sortByDesc('contracts_completed')
            ->values()
            ->all();
    }

    private function revenueByState(): array
    {
        $from = now()->startOfMonth();
        $feePercent = (float) config('quests.platform_fee_percent_display', 5);

        return DB::table('quests')
            ->leftJoin('states', 'states.id', '=', 'quests.state_id')
            ->whereBetween('quests.completed_at', [$from, now()])
            ->selectRaw("coalesce(states.name, quests.city, 'Unknown') as label, count(*) as contracts_completed, coalesce(sum(quests.paid_out_minor), 0) as gmv_minor")
            ->groupBy('label')
            ->orderByDesc('gmv_minor')
            ->limit(40)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'contracts_completed' => (int) $row->contracts_completed,
                'gmv' => $this->money((int) $row->gmv_minor),
                'service_fee' => $this->money((int) round(((int) $row->gmv_minor) * ($feePercent / 100))),
                'fee_percent' => $feePercent.'%',
                'mom_change' => '—',
            ])
            ->all();
    }

    private function change(int $current, int $previous): ?string
    {
        if ($previous === 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        return (($current - $previous) >= 0 ? '+' : '').round((($current - $previous) / $previous) * 100, 1).'%';
    }

    private function actionDescription(string $type, array $data): string
    {
        return match ($type) {
            'manual_release' => 'Manual escrow release'.(! empty($data['milestone']) ? ' for '.$data['milestone'] : ''),
            'manual_hold' => 'Manual escrow hold',
            'freeze' => 'Escrow frozen for dispute/investigation',
            'unfreeze' => 'Escrow unfrozen by super admin',
            'full_refund' => 'Full refund to client',
            'partial_refund' => 'Partial refund to client with split release',
            default => 'Manual financial adjustment',
        };
    }

    public function money(int|float $minor): string
    {
        $prefix = $minor < 0 ? '-' : '';

        return $prefix.'₦'.number_format(abs((int) $minor) / 100, 2);
    }
}
