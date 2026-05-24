<?php

namespace App\Services\Admin;

use App\Models\AdminFinancialLedgerEntry;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Support\NgnMoney;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminPlatformFeeLedgerService
{
    /**
     * @return array{today: string, week: string, month: string}
     */
    public function summaryTiles(): array
    {
        $tz = config('app.timezone', 'Africa/Lagos');
        $todayStart = now($tz)->startOfDay()->utc();
        $weekStart = now($tz)->startOfWeek()->utc();
        $monthStart = now($tz)->startOfMonth()->utc();

        $earnedQuery = AdminFinancialLedgerEntry::query()
            ->where('type', 'platform_fee')
            ->where('fee_amount_minor', '>', 0);

        return [
            'today' => NgnMoney::format((int) (clone $earnedQuery)->where('occurred_at', '>=', $todayStart)->sum('fee_amount_minor')),
            'week' => NgnMoney::format((int) (clone $earnedQuery)->where('occurred_at', '>=', $weekStart)->sum('fee_amount_minor')),
            'month' => NgnMoney::format((int) (clone $earnedQuery)->where('occurred_at', '>=', $monthStart)->sum('fee_amount_minor')),
        ];
    }

    /**
     * @return array{rows: \Illuminate\Contracts\Pagination\LengthAwarePaginator, filters: array<string, mixed>, sort_options: list<string>}
     */
    public function index(Request $request): array
    {
        $perPage = min(50, max(10, $request->integer('per_page', 20)));
        $sort = (string) $request->input('sort', 'funded_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $feeStatus = (string) $request->input('fee_status', '');
        $from = $request->filled('from') ? Carbon::parse($request->input('from'), config('app.timezone'))->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'), config('app.timezone'))->endOfDay() : null;
        $q = trim((string) $request->input('q', ''));

        $query = Quest::query()
            ->whereNotNull('accepted_quest_offer_id')
            ->with([
                'client:id,name,email',
                'freelancer:id,name,email',
                'acceptedOffer:id,quest_id,quoted_amount_minor,pricing_snapshot,planned_start_date,planned_finish_date,accepted_at',
                'paymentEscrow:id,quest_id,reference,amount_minor,fee_minor,status,funded_at,released_at',
            ]);

        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('slug', 'like', '%'.$q.'%')
                    ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))
                    ->orWhereHas('freelancer', fn (Builder $f) => $f->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))
                    ->orWhereHas('paymentEscrow', fn (Builder $e) => $e->where('reference', 'like', '%'.$q.'%'));
            });
        }

        if ($feeStatus === 'earned') {
            $query->where(function (Builder $sub): void {
                $sub->where('escrow_status', 'released')
                    ->orWhereHas('adminFinancialLedgerEntries', fn (Builder $l) => $l->where('type', 'platform_fee'));
            });
        } elseif ($feeStatus === 'pending') {
            $query->whereNotNull('escrow_funded_at')
                ->whereNotIn('escrow_status', ['released', 'refunded', 'awaiting_funding']);
        }

        if ($from !== null || $to !== null) {
            $query->where(function (Builder $sub) use ($from, $to): void {
                if ($from !== null && $to !== null) {
                    $sub->whereBetween('escrow_funded_at', [$from, $to])
                        ->orWhereBetween('completed_at', [$from, $to])
                        ->orWhereHas('paymentEscrow', fn (Builder $e) => $e->whereBetween('released_at', [$from, $to]));
                } elseif ($from !== null) {
                    $sub->where('escrow_funded_at', '>=', $from)
                        ->orWhere('completed_at', '>=', $from)
                        ->orWhereHas('paymentEscrow', fn (Builder $e) => $e->where('released_at', '>=', $from));
                } else {
                    $sub->where('escrow_funded_at', '<=', $to)
                        ->orWhere('completed_at', '<=', $to)
                        ->orWhereHas('paymentEscrow', fn (Builder $e) => $e->where('released_at', '<=', $to));
                }
            });
        }

        $sortColumn = match ($sort) {
            'quest_title' => 'title',
            'funded_at' => 'escrow_funded_at',
            'earned_at' => 'completed_at',
            'platform_fee' => 'paid_out_minor',
            'fee_status' => 'escrow_status',
            default => 'escrow_funded_at',
        };

        $paginator = $query->orderBy($sortColumn, $direction)->paginate($perPage)->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Quest $quest) => $this->row($quest))
        );

        return [
            'rows' => $paginator,
            'filters' => [
                'q' => $q,
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'fee_status' => $feeStatus,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'sort_options' => [
                ['key' => 'funded_at', 'label' => 'Funded date'],
                ['key' => 'earned_at', 'label' => 'Earned / completed'],
                ['key' => 'quest_title', 'label' => 'Quest title'],
                ['key' => 'platform_fee', 'label' => 'Fee amount'],
                ['key' => 'fee_status', 'label' => 'Fee status'],
            ],
            'tiles' => $this->summaryTiles(),
            'fee_percent' => PlatformSettings::platformFeePercent(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Quest $quest): array
    {
        $offer = $quest->acceptedOffer;
        $pricing = is_array($offer?->pricing_snapshot) ? $offer->pricing_snapshot : [];
        $escrow = $quest->paymentEscrow;

        $quotedFee = (int) ($pricing['platform_fee_minor'] ?? 0);
        $vatMinor = (int) ($pricing['vat_minor'] ?? 0);
        $discountMinor = (int) ($pricing['discount_minor'] ?? 0);
        $grandMinor = (int) ($offer?->quoted_amount_minor ?? $pricing['grand_total_minor'] ?? 0);

        $realizedFee = (int) ($escrow?->fee_minor ?? 0);
        if ($realizedFee === 0) {
            $ledgerFee = (int) AdminFinancialLedgerEntry::query()
                ->where('quest_id', $quest->id)
                ->where('type', 'platform_fee')
                ->sum('fee_amount_minor');
            $realizedFee = $ledgerFee;
        }

        $earned = $quest->escrow_status === 'released'
            || $realizedFee > 0
            || AdminFinancialLedgerEntry::query()
                ->where('quest_id', $quest->id)
                ->where('type', 'platform_fee')
                ->exists();

        $earnedAt = $escrow?->released_at ?? $quest->completed_at;

        $feeDisplay = $earned && $realizedFee > 0 ? $realizedFee : ($quotedFee > 0 ? $quotedFee : (int) round($grandMinor * (PlatformSettings::platformFeePercent() / 100)));

        $jobStart = $offer?->planned_start_date?->toDateString()
            ?? $quest->escrow_funded_at?->timezone(config('app.timezone'))->toDateString();
        $jobEnd = $quest->expectedCompletionAnchor()?->toDateString()
            ?? $offer?->planned_finish_date?->toDateString()
            ?? $quest->due_at?->toDateString();

        return [
            'id' => $quest->id,
            'quest_title' => $quest->title,
            'quest_route_key' => $quest->getRouteKey(),
            'proposal_id' => $offer?->id,
            'contract_ref' => $escrow?->reference ?? ('Q-'.$quest->id),
            'client' => $quest->client?->name,
            'client_email' => $quest->client?->email,
            'freelancer' => $quest->freelancer?->name,
            'freelancer_email' => $quest->freelancer?->email,
            'grand_total' => NgnMoney::format($grandMinor),
            'grand_total_minor' => $grandMinor,
            'vat' => NgnMoney::format($vatMinor),
            'discount' => NgnMoney::format($discountMinor),
            'platform_fee' => NgnMoney::format($feeDisplay),
            'platform_fee_minor' => $feeDisplay,
            'quoted_platform_fee' => NgnMoney::format($quotedFee),
            'realized_platform_fee' => $realizedFee > 0 ? NgnMoney::format($realizedFee) : null,
            'fee_status' => $earned ? 'earned' : 'pending',
            'fee_status_label' => $earned ? 'Earned' : 'Not yet earned',
            'quest_status' => $quest->status?->value ?? (string) $quest->status,
            'escrow_status' => $quest->escrow_status,
            'closure_type' => $quest->closure_type,
            'funded_at' => $quest->escrow_funded_at?->timezone(config('app.timezone'))->toIso8601String(),
            'earned_at' => $earnedAt?->timezone(config('app.timezone'))->toIso8601String(),
            'job_start' => $jobStart,
            'job_end' => $jobEnd,
            'accepted_at' => $offer?->accepted_at?->timezone(config('app.timezone'))->toIso8601String(),
            'admin_quest_url' => route('admin.quests.index', ['q' => $quest->title]),
        ];
    }

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->merge(['per_page' => 5000]);
        $rows = $this->index($request)['rows']->items();

        return \App\Support\AdminCsv::download('platform-fees-'.now()->format('Y-m-d').'.csv', [
            'Quest', 'Contract', 'Client', 'Freelancer', 'Grand total', 'VAT', 'Discount', 'Quoted fee', 'Realized fee', 'Status', 'Funded', 'Earned', 'Job start', 'Job end',
        ], function ($out) use ($rows): void {
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['quest_title'],
                    $row['contract_ref'],
                    $row['client'],
                    $row['freelancer'],
                    $row['grand_total'],
                    $row['vat'],
                    $row['discount'],
                    $row['quoted_platform_fee'],
                    $row['realized_platform_fee'] ?? '',
                    $row['fee_status_label'],
                    $row['funded_at'] ?? '',
                    $row['earned_at'] ?? '',
                    $row['job_start'] ?? '',
                    $row['job_end'] ?? '',
                ]);
            }
        });
    }
}
