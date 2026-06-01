<?php

namespace App\Services\Admin;

use App\Enums\LedgerAccount;
use App\Enums\LedgerEventType;
use App\Enums\QuestBoostStatus;
use App\Enums\QuestBoostTier;
use App\Enums\QuestStatus;
use App\Models\AdminFinancialLedgerEntry;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\QuestBoostAuditLog;
use App\Models\User;
use App\Notifications\QuestBoostEndedNotification;
use App\Notifications\QuestBoostGrantedNotification;
use App\Services\Finance\DoubleEntryLedgerService;
use App\Support\PlatformSettings;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QuestBoostService
{
    public function __construct(private readonly DoubleEntryLedgerService $ledger) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request): array
    {
        $query = $this->filteredQuery($request);

        return [
            'filters' => [
                'q' => $request->query('q'),
                'status' => $request->query('status'),
                'tier' => $request->query('tier'),
                'granting_admin_id' => $request->query('granting_admin_id'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
            'tiers' => $this->tierOptions(),
            'boosts' => $query->paginate(25)->withQueryString()->through(fn (QuestBoost $b) => $this->listRow($b)),
            'metrics' => [
                'active' => QuestBoost::query()->activeNow()->count(),
                'investment_month_minor' => (int) QuestBoost::query()
                    ->where('granted_at', '>=', now()->startOfMonth())
                    ->sum('planned_cost_minor'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(QuestBoost $boost): array
    {
        $boost->load(['quest:id,title,reference_code,status', 'client:id,name,email', 'grantedByAdmin:id,name,email', 'auditLogs.actor:id,name,email']);

        return [
            'boost' => $this->detailRow($boost),
            'audit_trail' => $boost->auditLogs->map(fn (QuestBoostAuditLog $log) => [
                'id' => $log->id,
                'action_type' => $log->action_type,
                'actor' => $log->actor?->only(['id', 'name', 'email']),
                'reason' => $log->reason,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function grant(array $data, User $admin): QuestBoost
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $quest = Quest::query()->with('client')->findOrFail((int) $data['quest_id']);
        if (! in_array($quest->status, [QuestStatus::Open, QuestStatus::Assigned, QuestStatus::InProgress], true)) {
            throw ValidationException::withMessages(['quest_id' => [__('Only active quests can receive boosts.')]]);
        }

        $tier = QuestBoostTier::from((string) $data['tier']);
        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
        $endsAt = isset($data['ends_at'])
            ? Carbon::parse($data['ends_at'])
            : $startsAt->copy()->addHours($tier->durationHours());
        $costMinor = PlatformSettings::questBoostPriceMinor($tier);

        return DB::transaction(function () use ($quest, $admin, $tier, $startsAt, $endsAt, $costMinor, $data): QuestBoost {
            $boost = QuestBoost::query()->create([
                'quest_id' => $quest->id,
                'quest_title_snapshot' => $quest->title,
                'client_id' => $quest->client_id,
                'granted_by_admin_id' => $admin->id,
                'tier' => $tier->value,
                'planned_cost_minor' => $costMinor,
                'status' => QuestBoostStatus::Active->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'grant_reason' => (string) $data['grant_reason'],
                'granted_at' => now(),
                'visibility_tier' => 'tier_1',
            ]);

            $this->audit($boost, 'granted', $admin->id, null, [
                'tier' => $tier->value,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'planned_cost_minor' => $costMinor,
                'grant_reason' => $data['grant_reason'],
            ], $data['grant_reason']);

            $this->recordPromotionalSpend($boost, $admin);

            $quest->client?->notify(new QuestBoostGrantedNotification($boost));

            return $boost;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDates(QuestBoost $boost, array $data, User $admin): QuestBoost
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        if ($boost->status !== QuestBoostStatus::Active->value) {
            throw ValidationException::withMessages(['boost' => [__('Only active boosts can be edited.')]]);
        }

        $old = [
            'starts_at' => $boost->starts_at?->toIso8601String(),
            'ends_at' => $boost->ends_at?->toIso8601String(),
        ];

        $boost->forceFill([
            'starts_at' => Carbon::parse($data['starts_at']),
            'ends_at' => Carbon::parse($data['ends_at']),
        ])->save();

        $this->audit($boost, 'date_edited', $admin->id, $old, [
            'starts_at' => $boost->starts_at?->toIso8601String(),
            'ends_at' => $boost->ends_at?->toIso8601String(),
        ], $data['reason'] ?? null);

        return $boost->fresh();
    }

    public function endEarly(QuestBoost $boost, User $admin, string $reason): QuestBoost
    {
        return $this->closeBoost($boost, $admin, QuestBoostStatus::ManuallyEndedEarly, 'ended_early', $reason);
    }

    public function cancel(QuestBoost $boost, User $admin, string $reason): QuestBoost
    {
        return $this->closeBoost($boost, $admin, QuestBoostStatus::ManuallyCancelled, 'cancelled', $reason);
    }

    public function expireDueBoosts(): int
    {
        $count = 0;
        QuestBoost::query()
            ->where('status', QuestBoostStatus::Active->value)
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($boosts) use (&$count): void {
                foreach ($boosts as $boost) {
                    $this->expireOne($boost);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchQuests(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        return Quest::query()
            ->with(['client:id,name,email', 'questCategory:id,name'])
            ->whereIn('status', [QuestStatus::Open, QuestStatus::Assigned, QuestStatus::InProgress])
            ->where(function (Builder $q) use ($term): void {
                $q->where('title', 'like', '%'.$term.'%')
                    ->orWhere('reference_code', 'like', '%'.$term.'%');
                if (is_numeric($term)) {
                    $q->orWhere('id', (int) $term);
                }
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Quest $quest) => [
                'id' => $quest->id,
                'title' => $quest->title,
                'reference_code' => $quest->reference_code,
                'client' => $quest->client?->only(['id', 'name', 'email']),
                'category' => $quest->questCategory?->name,
                'status' => $quest->status?->value,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tierOptions(): array
    {
        $prices = PlatformSettings::questBoostPricing();

        return collect(QuestBoostTier::ordered())->map(fn (QuestBoostTier $tier) => [
            'value' => $tier->value,
            'label' => $tier->label(),
            'hours' => $tier->durationHours(),
            'price_minor' => $prices[$tier->value] ?? 0,
            'price_display' => \App\Support\NgnMoney::format((int) ($prices[$tier->value] ?? 0)),
        ])->all();
    }

    /**
     * @return list<int>
     */
    public function activeBoostedQuestIds(?int $categoryId = null): array
    {
        $query = QuestBoost::query()->activeNow()->orderBy('ends_at');

        if ($categoryId !== null) {
            $query->whereHas('quest', fn (Builder $q) => $q->where('quest_category_id', $categoryId));
        }

        return $query->pluck('quest_id')->unique()->values()->all();
    }

    private function closeBoost(QuestBoost $boost, User $admin, QuestBoostStatus $status, string $action, string $reason): QuestBoost
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        if ($boost->status !== QuestBoostStatus::Active->value) {
            throw ValidationException::withMessages(['boost' => [__('This boost is not active.')]]);
        }

        $boost->forceFill([
            'status' => $status->value,
            'actual_ended_at' => now(),
        ])->save();

        $this->audit($boost, $action, $admin->id, ['status' => QuestBoostStatus::Active->value], [
            'status' => $status->value,
            'actual_ended_at' => $boost->actual_ended_at?->toIso8601String(),
        ], $reason);

        $boost->client?->notify(new QuestBoostEndedNotification($boost, $reason));

        return $boost->fresh();
    }

    private function expireOne(QuestBoost $boost): void
    {
        if ($boost->status !== QuestBoostStatus::Active->value) {
            return;
        }

        $boost->forceFill([
            'status' => QuestBoostStatus::Expired->value,
            'actual_ended_at' => now(),
        ])->save();

        $this->audit($boost, 'expired', null, ['status' => QuestBoostStatus::Active->value], [
            'status' => QuestBoostStatus::Expired->value,
        ], 'Automatic expiry at scheduled end time.');

        $boost->client?->notify(new QuestBoostEndedNotification($boost, __('Your quest boost period has ended.')));
    }

    private function recordPromotionalSpend(QuestBoost $boost, User $admin): void
    {
        AdminFinancialLedgerEntry::query()->create([
            'quest_id' => $boost->quest_id,
            'client_id' => $boost->client_id,
            'admin_user_id' => $admin->id,
            'type' => 'promotional_spend',
            'direction' => 'outflow',
            'source' => 'quest_boost',
            'status' => 'completed',
            'description' => __('Admin-granted quest boost investment'),
            'gross_amount_minor' => (int) $boost->planned_cost_minor,
            'fee_amount_minor' => 0,
            'net_amount_minor' => (int) $boost->planned_cost_minor,
            'admin_reason' => $boost->grant_reason,
            'meta' => [
                'quest_boost_id' => $boost->id,
                'quest_boost_reference' => $boost->reference,
                'tier' => $boost->tier,
            ],
            'occurred_at' => $boost->granted_at ?? now(),
        ]);

        $amount = (int) $boost->planned_cost_minor;
        if ($amount <= 0) {
            return;
        }

        $this->ledger->postBalancedBatch(
            LedgerEventType::BoostInvestment,
            'quest-boost:'.$boost->id,
            [
                ['account' => LedgerAccount::PromotionalSpend, 'side' => 'debit', 'amount_minor' => $amount],
                ['account' => LedgerAccount::PromotionalSpendClearing, 'side' => 'credit', 'amount_minor' => $amount],
            ],
            'QuestBoostService',
            [
                'quest_id' => $boost->quest_id,
                'client_id' => $boost->client_id,
                'meta' => ['quest_boost_reference' => $boost->reference],
                'occurred_at' => $boost->granted_at ?? now(),
            ],
            __('Quest boost promotional investment'),
        );
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(
        QuestBoost $boost,
        string $action,
        ?int $actorId,
        ?array $old,
        array $new,
        ?string $reason,
    ): void {
        QuestBoostAuditLog::query()->create([
            'quest_boost_id' => $boost->id,
            'action_type' => $action,
            'actor_user_id' => $actorId,
            'reason' => $reason,
            'old_values' => $old,
            'new_values' => $new,
            'occurred_at' => now(),
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = QuestBoost::query()
            ->with(['quest:id,title,reference_code', 'client:id,name,email', 'grantedByAdmin:id,name,email'])
            ->latest('granted_at');

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function (Builder $q) use ($term): void {
                $q->where('reference', 'like', '%'.$term.'%')
                    ->orWhere('quest_title_snapshot', 'like', '%'.$term.'%')
                    ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', '%'.$term.'%'));
                if (is_numeric($term)) {
                    $q->orWhere('quest_id', (int) $term);
                }
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->input('tier'));
        }

        if ($request->filled('granting_admin_id')) {
            $query->where('granted_by_admin_id', (int) $request->input('granting_admin_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('granted_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('granted_at', '<=', $request->input('to'));
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(QuestBoost $boost): array
    {
        return [
            'id' => $boost->id,
            'reference' => $boost->reference,
            'quest_id' => $boost->quest_id,
            'quest_title' => $boost->quest_title_snapshot,
            'client_name' => $boost->client?->name,
            'tier' => $boost->tier,
            'tier_label' => $boost->tierEnum()->label(),
            'planned_cost_display' => \App\Support\NgnMoney::format((int) $boost->planned_cost_minor),
            'starts_at' => $boost->starts_at?->toIso8601String(),
            'ends_at' => $boost->ends_at?->toIso8601String(),
            'grant_reason' => $boost->grant_reason,
            'granting_admin' => $boost->grantedByAdmin?->name,
            'status' => $boost->status,
            'status_label' => $boost->statusEnum()->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailRow(QuestBoost $boost): array
    {
        return [
            ...$this->listRow($boost),
            'quest' => $boost->quest?->only(['id', 'title', 'reference_code', 'status']),
            'client' => $boost->client?->only(['id', 'name', 'email']),
            'granting_admin' => $boost->grantedByAdmin?->only(['id', 'name', 'email']),
            'actual_ended_at' => $boost->actual_ended_at?->toIso8601String(),
            'granted_at' => $boost->granted_at?->toIso8601String(),
            'visibility_tier' => $boost->visibility_tier,
            'planned_cost_minor' => (int) $boost->planned_cost_minor,
            'actual_duration_hours' => $boost->actual_ended_at
                ? round($boost->starts_at->diffInMinutes($boost->actual_ended_at) / 60, 1)
                : round($boost->starts_at->diffInMinutes($boost->ends_at) / 60, 1),
        ];
    }
}
