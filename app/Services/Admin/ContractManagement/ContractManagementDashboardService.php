<?php

namespace App\Services\Admin\ContractManagement;

use App\Enums\ContractStatus;
use App\Enums\QuestDisputeStatus;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestContract;
use App\Models\QuestContractEvent;
use App\Models\QuestDispute;
use App\Models\State;
use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class ContractManagementDashboardService
{
    public function __construct(
        private readonly \App\Services\Contracts\ContractRegistrySyncService $registrySync,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request, bool $isSuperAdmin): array
    {
        $this->ensureRegistrySynced();
        $patrol = app(ContractPatrolAnomalyService::class);
        $savedFilters = app(ContractManagementSavedFilterService::class);

        return [
            'overview' => $this->overviewStats(),
            'system_operations' => $isSuperAdmin ? $this->systemOperationsStats() : null,
            'alerts' => $this->alerts(limit: 20),
            'patrol_flags' => $patrol->openFlags(40),
            'quick_counts' => $this->quickCounts($request->user()),
            'listing' => $this->listing($request),
            'disputes' => $this->disputeListing($request),
            'filter_options' => $this->filterOptions(),
            'saved_filters' => $savedFilters->listForUser($request->user()),
            'settings' => $isSuperAdmin ? app(ContractManagementSettingsService::class)->payload() : null,
            'is_super_admin' => $isSuperAdmin,
            'capabilities' => $this->capabilities($isSuperAdmin),
            'registry' => $this->registryMeta(),
            'refreshed_at' => now()->timezone('Africa/Lagos')->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listing(Request $request): array
    {
        $this->ensureRegistrySynced();

        $query = $this->baseQuery($request);
        $this->applySort($query, (string) $request->query('sort', 'recent'));

        $perPage = min(100, max(15, $request->integer('per_page', 25)));
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'items' => collect($paginator->items())->map(fn (QuestContract $c) => $this->contractRow($c))->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function disputeListing(Request $request): array
    {
        $query = QuestDispute::query()
            ->with([
                'quest:id,title,slug,uuid',
                'quest.contract:id,quest_id,reference_code,status',
                'openedBy:id,name,username',
            ])
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = '%'.str_replace(['%', '_'], '', trim((string) $request->query('q'))).'%';
                $q->where(function (Builder $scope) use ($term): void {
                    $scope->where('reason', 'like', $term)
                        ->orWhereHas('quest.contract', fn (Builder $c) => $c->where('reference_code', 'like', $term))
                        ->orWhereHas('quest', fn (Builder $quest) => $quest->where('title', 'like', $term));
                });
            })
            ->when($request->filled('dispute_status'), function (Builder $q) use ($request): void {
                $status = (string) $request->query('dispute_status');
                if ($status === 'active') {
                    $q->whereNull('resolved_at');
                } elseif ($status === 'resolved') {
                    $q->whereNotNull('resolved_at');
                }
            })
            ->orderByDesc('created_at');

        $perPage = min(50, max(10, $request->integer('dispute_per_page', 15)));
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'items' => collect($paginator->items())->map(fn (QuestDispute $d) => [
                'id' => $d->id,
                'uuid' => $d->uuid,
                'reference' => $d->quest?->contract?->reference_code,
                'quest_title' => $d->quest?->title,
                'reason' => Str::limit((string) $d->reason, 120),
                'status' => $d->status instanceof QuestDisputeStatus ? $d->status->value : (string) $d->status,
                'status_label' => $d->status instanceof QuestDisputeStatus ? str_replace('_', ' ', ucfirst($d->status->value)) : (string) $d->status,
                'amount_minor' => (int) ($d->disputed_amount_minor ?? 0),
                'amount_formatted' => NgnMoney::format((int) ($d->disputed_amount_minor ?? 0)),
                'filed_by' => $d->openedBy?->name,
                'filed_at' => $d->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'filed_ago' => $d->created_at?->diffForHumans(),
                'is_resolved' => $d->resolved_at !== null,
            ])->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function alerts(int $limit = 20, ?string $type = null): array
    {
        $contracts = QuestContract::query()
            ->with([
                'quest:id,title,escrow_status,delivery_acknowledged_at,latest_delivery_submission_id',
                'client:id,name',
                'freelancer:id,name',
            ])
            ->whereIn('status', [
                ContractStatus::PendingEscrow,
                ContractStatus::Active,
                ContractStatus::AmendmentPending,
                ContractStatus::Disputed,
            ])
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $alerts = $contracts
            ->flatMap(fn (QuestContract $c) => $this->contractAlerts($c))
            ->sortByDesc(fn (array $a) => match ($a['severity']) {
                'critical' => 4,
                'high' => 3,
                'medium' => 2,
                default => 1,
            })
            ->values();

        $patrolAlerts = collect(app(ContractPatrolAnomalyService::class)->openFlags(100))
            ->map(fn (array $flag) => [
                'id' => 'patrol:'.$flag['id'],
                'contract_id' => $flag['contract_id'],
                'reference_code' => $flag['reference_code'],
                'type' => 'patrol',
                'patrol_flag_id' => $flag['id'],
                'severity' => $flag['severity'],
                'title' => $flag['type_label'] ?? 'Patrol flag',
                'message' => '['.($flag['reference_code'] ?? '—').'] '.($flag['summary'] ?? ''),
                'detected_ago' => $flag['detected_ago'] ?? null,
            ]);

        $alerts = $alerts->concat($patrolAlerts)
            ->sortByDesc(fn (array $a) => match ($a['severity']) {
                'critical' => 4,
                'high' => 3,
                'medium' => 2,
                default => 1,
            })
            ->values();

        if ($type !== null && $type !== '') {
            $alerts = $alerts->filter(fn (array $a) => $a['type'] === $type)->values();
        }

        return $alerts->take($limit)->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function overviewStats(): array
    {
        $active = QuestContract::query()->where('status', ContractStatus::Active)->count();
        $pendingEscrow = QuestContract::query()->where('status', ContractStatus::PendingEscrow)->count();
        $disputed = QuestContract::query()->where('status', ContractStatus::Disputed)->count();
        $overdue = $this->overdueContractCount();
        $awaitingApproval = Quest::query()
            ->whereNotNull('latest_delivery_submission_id')
            ->whereNull('delivery_acknowledged_at')
            ->whereHas('contract', fn (Builder $q) => $q->where('status', ContractStatus::Active))
            ->count();

        $escrowHeldMinor = (int) PaymentEscrow::query()
            ->whereIn('status', ['funded', 'held'])
            ->selectRaw('COALESCE(SUM(amount_minor - released_minor - refunded_minor), 0) as held')
            ->value('held');

        return [
            'active_contracts' => $active,
            'awaiting_approval' => $awaitingApproval,
            'in_dispute' => $disputed,
            'overdue' => $overdue,
            'pending_escrow' => $pendingEscrow,
            'escrow_held_minor' => $escrowHeldMinor,
            'escrow_held_formatted' => NgnMoney::format($escrowHeldMinor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function systemOperationsStats(): array
    {
        $today = now()->timezone('Africa/Lagos')->startOfDay();

        $revenueTodayMinor = (int) PaymentEscrow::query()
            ->whereDate('funded_at', $today)
            ->sum('fee_minor');

        $payoutsTodayMinor = (int) WalletWithdrawal::query()
            ->where('status', 'completed')
            ->whereDate('processed_at', $today)
            ->sum('amount_minor');

        $terminatedWeek = QuestContract::query()
            ->where('status', ContractStatus::Cancelled)
            ->where('cancelled_at', '>=', now()->subDays(7))
            ->count();

        return [
            'revenue_today_minor' => $revenueTodayMinor,
            'revenue_today_formatted' => NgnMoney::format($revenueTodayMinor),
            'payouts_today_minor' => $payoutsTodayMinor,
            'payouts_today_formatted' => NgnMoney::format($payoutsTodayMinor),
            'terminated_this_week' => $terminatedWeek,
            'escrow_reconciled' => true,
            'escrow_reconciled_label' => 'Balanced',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quickCounts(?User $user): array
    {
        $staffId = $user?->id;

        return [
            'critical' => collect($this->alerts(limit: 100))->where('severity', 'critical')->count(),
            'flagged_mine' => $staffId
                ? QuestContract::query()->where('flagged_for_review', true)->where('flagged_for_review_by', $staffId)->count()
                : 0,
            'assigned_mine' => $staffId
                ? QuestContractEvent::query()
                    ->where('event_type', 'contract.staff_assigned')
                    ->where('properties->assigned_to', $staffId)
                    ->distinct('quest_contract_id')
                    ->count('quest_contract_id')
                : 0,
            'disputes_open' => QuestDispute::query()->whereNull('resolved_at')->count(),
            'needs_review' => QuestContract::query()->where('flagged_for_review', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'contract_statuses' => collect(ContractStatus::cases())->map(fn (ContractStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
            'delivery_statuses' => config('contract_management.delivery_statuses', []),
            'payment_statuses' => config('contract_management.payment_statuses', []),
            'dispute_statuses' => config('contract_management.dispute_statuses', []),
            'risk_levels' => config('contract_management.risk_levels', []),
            'sort_options' => config('contract_management.sort_options', []),
            'alert_types' => config('contract_management.alert_types', []),
            'categories' => QuestCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->name,
            ])->all(),
            'states' => State::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => [
                'value' => $s->id,
                'label' => $s->name,
            ])->all(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(bool $isSuperAdmin): array
    {
        return [
            'view' => true,
            'flag' => true,
            'note' => true,
            'quality_review' => true,
            'assign' => true,
            'export_csv' => true,
            'export_pdf' => true,
            'saved_filters' => true,
            'patrol_flags' => true,
            'message_transcript' => true,
            'quality_audit' => true,
            'bulk_operations' => $isSuperAdmin,
            'release_payment' => $isSuperAdmin,
            'partial_release' => $isSuperAdmin,
            'force_delivery' => $isSuperAdmin,
            'hold_escrow' => $isSuperAdmin,
            'terminate' => $isSuperAdmin,
            'edit_terms' => $isSuperAdmin,
            'resolve_dispute' => $isSuperAdmin,
            'reconcile_escrow' => $isSuperAdmin,
            'system_settings' => $isSuperAdmin,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(Request $request): array
    {
        $request->merge(['per_page' => 5000]);
        $query = $this->baseQuery($request);
        $this->applySort($query, (string) $request->query('sort', 'recent'));

        return $query->limit(5000)->get()->map(fn (QuestContract $c) => $this->contractRow($c))->all();
    }

    private function baseQuery(Request $request): Builder
    {
        $query = QuestContract::query()
            ->with([
                'quest:id,title,slug,uuid,reference_code,escrow_status,budget_amount_minor,quest_category_id,state_id,delivery_acknowledged_at,delivery_revision_requested_at,latest_delivery_submission_id,delivery_review_deadline_at',
                'quest.questCategory:id,name',
                'quest.stateModel:id,name',
                'client:id,name,username,current_verification_level',
                'freelancer:id,name,username,current_verification_level',
                'activeDispute:id,status,resolved_at',
            ]);

        if ($q = trim((string) $request->query('q', ''))) {
            $term = '%'.str_replace(['%', '_'], '', $q).'%';
            $query->where(function (Builder $scope) use ($term): void {
                $scope->where('reference_code', 'like', $term)
                    ->orWhereHas('quest', fn (Builder $quest) => $quest
                        ->where('title', 'like', $term)
                        ->orWhere('reference_code', 'like', $term))
                    ->orWhereHas('client', fn (Builder $u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('freelancer', fn (Builder $u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        if ($request->filled('status')) {
            $statuses = Arr::wrap($request->query('status'));
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('risk_level')) {
            $risk = (string) $request->query('risk_level');
            $query->where(function (Builder $scope) use ($risk): void {
                $ids = QuestContract::query()->limit(2000)->get()->filter(
                    fn (QuestContract $c) => $this->assessRisk($c)['level'] === $risk
                )->pluck('id');
                $scope->whereIn('id', $ids->isEmpty() ? [-1] : $ids);
            });
        }

        if ($request->boolean('flagged')) {
            $query->where('flagged_for_review', true);
        }

        if ($request->filled('dispute_status')) {
            $disputeStatus = (string) $request->query('dispute_status');
            if ($disputeStatus === 'none') {
                $query->whereNull('active_dispute_id')->where('status', '!=', ContractStatus::Disputed);
            } elseif ($disputeStatus === 'active') {
                $query->where(function (Builder $scope): void {
                    $scope->whereNotNull('active_dispute_id')->orWhere('status', ContractStatus::Disputed);
                });
            } elseif ($disputeStatus === 'resolved') {
                $query->whereHas('activeDispute', fn (Builder $d) => $d->whereNotNull('resolved_at'));
            }
        }

        if ($request->filled('payment_status')) {
            $payment = (string) $request->query('payment_status');
            $query->whereHas('quest', fn (Builder $q) => $q->where('escrow_status', $payment));
        }

        if ($request->filled('delivery_status')) {
            $delivery = (string) $request->query('delivery_status');
            $query->whereHas('quest', function (Builder $q) use ($delivery): void {
                match ($delivery) {
                    'pending' => $q->whereNull('latest_delivery_submission_id'),
                    'submitted' => $q->whereNotNull('latest_delivery_submission_id')->whereNull('delivery_acknowledged_at')->whereNull('delivery_revision_requested_at'),
                    'revision' => $q->whereNotNull('delivery_revision_requested_at')->whereNull('delivery_acknowledged_at'),
                    'approved' => $q->whereNotNull('delivery_acknowledged_at'),
                    default => null,
                };
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('quest', fn (Builder $q) => $q->where('quest_category_id', $request->integer('category_id')));
        }

        if ($request->filled('state_id')) {
            $query->whereHas('quest', fn (Builder $q) => $q->where('state_id', $request->integer('state_id')));
        }

        if ($request->filled('amount_min')) {
            $min = $request->integer('amount_min');
            $query->whereRaw("(JSON_EXTRACT(financial_snapshot, '$.grand_total_minor') + 0) >= ?", [$min]);
        }

        if ($request->filled('amount_max')) {
            $max = $request->integer('amount_max');
            $query->whereRaw("(JSON_EXTRACT(financial_snapshot, '$.grand_total_minor') + 0) <= ?", [$max]);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('generated_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('generated_at', '<=', $request->query('date_to'));
        }

        if ($quick = (string) $request->query('quick_view', '')) {
            $query = $this->applyQuickView($query, $quick, $request->user());
        }

        return $query;
    }

    private function applyQuickView(Builder $query, string $quick, ?User $user): Builder
    {
        return match ($quick) {
            'critical' => $query->where(function (Builder $scope): void {
                $scope->where('status', ContractStatus::Disputed)
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('status', ContractStatus::Active)
                            ->whereNotNull('agreed_delivery_date')
                            ->whereDate('agreed_delivery_date', '<', now()->subDays(2));
                    });
            }),
            'overdue' => $query->where('status', ContractStatus::Active)
                ->whereNotNull('agreed_delivery_date')
                ->whereDate('agreed_delivery_date', '<', now()),
            'disputed' => $query->where(fn (Builder $q) => $q->where('status', ContractStatus::Disputed)->orWhereNotNull('active_dispute_id')),
            'flagged' => $query->where('flagged_for_review', true),
            'mine' => $user
                ? $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('flagged_for_review_by', $user->id)
                        ->orWhereIn('id', QuestContractEvent::query()
                            ->where('event_type', 'contract.staff_assigned')
                            ->where('properties->assigned_to', $user->id)
                            ->pluck('quest_contract_id'));
                })
                : $query->whereRaw('1 = 0'),
            'pending_escrow' => $query->where('status', ContractStatus::PendingEscrow),
            default => $query,
        };
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('generated_at'),
            'highest_value' => $query->orderByRaw("(JSON_EXTRACT(financial_snapshot, '$.grand_total_minor') + 0) DESC"),
            'lowest_value' => $query->orderByRaw("(JSON_EXTRACT(financial_snapshot, '$.grand_total_minor') + 0) ASC"),
            'due_soon' => $query->orderByRaw('agreed_delivery_date IS NULL')->orderBy('agreed_delivery_date'),
            'overdue' => $query->orderByRaw('agreed_delivery_date IS NULL')->orderByDesc('agreed_delivery_date'),
            'highest_risk' => $query->orderByDesc('updated_at'),
            default => $query->orderByDesc('generated_at'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function contractRow(QuestContract $contract): array
    {
        $quest = $contract->quest;
        $financial = is_array($contract->financial_snapshot) ? $contract->financial_snapshot : [];
        $amountMinor = (int) ($financial['grand_total_minor'] ?? $financial['total_minor'] ?? $quest?->budget_amount_minor ?? 0);
        $risk = $this->assessRisk($contract);
        $delivery = $this->deliveryStatus($contract);
        $dueDate = $contract->agreed_delivery_date;
        $daysUntilDue = $dueDate ? now()->startOfDay()->diffInDays($dueDate, false) : null;

        return [
            'id' => $contract->id,
            'reference_code' => $contract->reference_code,
            'quest_title' => $quest?->title,
            'quest_reference' => $quest?->reference_code,
            'quest_route_key' => $quest?->getRouteKey(),
            'client' => [
                'id' => $contract->client_id,
                'name' => $contract->client?->name,
                'tier' => (int) ($contract->client?->current_verification_level ?? 0),
            ],
            'freelancer' => [
                'id' => $contract->freelancer_id,
                'name' => $contract->freelancer?->name,
                'tier' => (int) ($contract->freelancer?->current_verification_level ?? 0),
            ],
            'category' => $quest?->questCategory?->name,
            'location' => $quest?->stateModel?->name,
            'amount_minor' => $amountMinor,
            'amount_formatted' => NgnMoney::format($amountMinor),
            'status' => $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status,
            'status_label' => $contract->status instanceof ContractStatus ? $contract->status->label() : (string) $contract->status,
            'payment_status' => $quest?->escrow_status,
            'delivery_status' => $delivery['value'],
            'delivery_status_label' => $delivery['label'],
            'dispute_active' => $contract->active_dispute_id !== null || $contract->status === ContractStatus::Disputed,
            'flagged_for_review' => (bool) $contract->flagged_for_review,
            'risk_level' => $risk['level'],
            'risk_label' => $risk['label'],
            'risk_reasons' => $risk['reasons'],
            'due_date' => $dueDate?->toDateString(),
            'due_label' => $dueDate?->format('j M Y'),
            'days_until_due' => $daysUntilDue,
            'is_overdue' => $daysUntilDue !== null && $daysUntilDue < 0 && $contract->status === ContractStatus::Active,
            'generated_at' => $contract->generated_at?->timezone('Africa/Lagos')->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contractAlerts(QuestContract $contract): array
    {
        $alerts = [];
        $risk = $this->assessRisk($contract);
        $ref = $contract->reference_code;

        if ($contract->status === ContractStatus::Disputed || $contract->active_dispute_id) {
            $alerts[] = $this->alertRow($contract, 'disputed', 'critical', 'Active dispute', "[{$ref}] Contract is in dispute.");
        }

        if ($contract->flagged_for_review) {
            $alerts[] = $this->alertRow($contract, 'flagged', 'high', 'Flagged for review', "[{$ref}] ".Str::limit((string) $contract->flagged_for_review_reason, 80));
        }

        if ($contract->status === ContractStatus::PendingEscrow) {
            $alerts[] = $this->alertRow($contract, 'pending_escrow', 'medium', 'Awaiting escrow funding', "[{$ref}] Contract generated but escrow not funded.");
        }

        if ($contract->status === ContractStatus::AmendmentPending) {
            $alerts[] = $this->alertRow($contract, 'amendment_pending', 'medium', 'Amendment pending', "[{$ref}] Contract amendment awaiting party response.");
        }

        $delivery = $this->deliveryStatus($contract);
        if ($delivery['value'] === 'submitted') {
            $alerts[] = $this->alertRow($contract, 'delivery_review', 'medium', 'Delivery awaiting client approval', "[{$ref}] Work submitted — awaiting client review.");
        }

        if ($risk['level'] === 'critical' && ($contract->status === ContractStatus::Active)) {
            $alerts[] = $this->alertRow($contract, 'overdue', 'critical', 'Overdue delivery', "[{$ref}] ".($risk['reasons'][0] ?? 'Past agreed delivery date.'));
        } elseif ($risk['level'] === 'high') {
            $alerts[] = $this->alertRow($contract, 'overdue', 'high', 'Contract needs attention', "[{$ref}] ".($risk['reasons'][0] ?? 'Elevated risk detected.'));
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>
     */
    private function alertRow(QuestContract $contract, string $type, string $severity, string $title, string $message): array
    {
        return [
            'id' => "{$type}:{$contract->id}",
            'contract_id' => $contract->id,
            'reference_code' => $contract->reference_code,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
        ];
    }

    /**
     * @return array{value: string, label: string}
     */
    private function deliveryStatus(QuestContract $contract): array
    {
        $quest = $contract->quest;
        if ($quest === null) {
            return ['value' => 'pending', 'label' => 'Pending'];
        }

        if ($quest->delivery_acknowledged_at !== null) {
            return ['value' => 'approved', 'label' => 'Approved'];
        }

        if ($quest->delivery_revision_requested_at !== null) {
            return ['value' => 'revision', 'label' => 'Revision requested'];
        }

        if ($quest->latest_delivery_submission_id !== null) {
            return ['value' => 'submitted', 'label' => 'Awaiting approval'];
        }

        return ['value' => 'pending', 'label' => 'Pending delivery'];
    }

    /**
     * @return array{level: string, label: string, reasons: list<string>}
     */
    public function assessRisk(QuestContract $contract): array
    {
        $reasons = [];
        $score = 0;
        $quest = $contract->quest;

        if ($contract->status === ContractStatus::Disputed || $contract->active_dispute_id) {
            $score += 80;
            $reasons[] = 'Active dispute';
        }

        if ($contract->flagged_for_review) {
            $score += 30;
            $reasons[] = 'Flagged for staff review';
        }

        if ($contract->status === ContractStatus::Active && $contract->agreed_delivery_date !== null) {
            $overdueDays = $contract->agreed_delivery_date->startOfDay()->diffInDays(now()->startOfDay(), false);
            if ($overdueDays > 0 && ($quest === null || $quest->latest_delivery_submission_id === null)) {
                $score += min(90, 40 + ($overdueDays * 10));
                $reasons[] = "Overdue {$overdueDays} day(s) — no delivery submitted";
            } elseif ($overdueDays > 0 && $quest?->latest_delivery_submission_id !== null && $quest->delivery_acknowledged_at === null) {
                $score += min(70, 25 + ($overdueDays * 5));
                $reasons[] = "Delivery submitted {$overdueDays} day(s) after deadline";
            } elseif ($overdueDays >= -1 && $overdueDays <= 0) {
                $score += 20;
                $reasons[] = 'Due within 24 hours';
            }
        }

        if ($contract->status === ContractStatus::PendingEscrow && $contract->escrow_expires_at !== null && $contract->escrow_expires_at->isPast()) {
            $score += 35;
            $reasons[] = 'Escrow funding window expired';
        }

        if ($contract->delivery_extension_count >= 2) {
            $score += 15;
            $reasons[] = 'Multiple delivery extensions';
        }

        $settings = app(ContractManagementSettingsService::class);
        $criticalThreshold = $settings->riskThreshold('critical');
        $highThreshold = $settings->riskThreshold('high');

        $level = match (true) {
            $score >= $criticalThreshold => 'critical',
            $score >= $highThreshold => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };

        return [
            'level' => $level,
            'label' => ucfirst($level),
            'reasons' => $reasons ?: ['No significant risk signals'],
            'score' => $score,
        ];
    }

    private function overdueContractCount(): int
    {
        return QuestContract::query()
            ->where('status', ContractStatus::Active)
            ->whereNotNull('agreed_delivery_date')
            ->whereDate('agreed_delivery_date', '<', now())
            ->whereHas('quest', fn (Builder $q) => $q->whereNull('delivery_acknowledged_at'))
            ->count();
    }

    private function ensureRegistrySynced(): void
    {
        if (! $this->registrySync->hasMissingContracts()) {
            return;
        }

        $this->registrySync->syncMissing();
    }

    /**
     * @return array<string, mixed>
     */
    private function registryMeta(): array
    {
        return [
            'total' => QuestContract::query()->count(),
            'definition' => 'A contract is an awarded engagement where the client and freelancer have mutually confirmed award terms. Each appears as a quest_contracts registry row (reference CTR-…). Legacy awards that funded escrow before contract generation are synced automatically when you open this console.',
            'includes' => [
                'Mutually confirmed awards (accepted proposal)',
                'Pending escrow and funded / in-progress engagements',
                'Completed, disputed, and cancelled contracts',
            ],
            'excludes' => [
                'Open quests with no award',
                'Pending award awaiting freelancer confirmation',
                'Declined or withdrawn proposals',
            ],
        ];
    }
}
