<?php

namespace App\Services\Admin;

use App\Models\AdminComplianceRequest;
use App\Models\AdminFinancialLedgerEntry;
use App\Models\AdminFraudCase;
use App\Models\AdminNotification;
use App\Services\Operations\StaffNotificationCentreService;
use App\Models\AdminRiskRule;
use App\Models\AdminTask;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminCommandCentreService
{
    public function __construct(private readonly StaffNotificationCentreService $notificationCentre) {}

    public function search(string $term): array
    {
        $q = trim($term);
        $actions = collect([
            ['label' => 'Suspend user', 'description' => 'Open User Management with sanction tools.', 'href' => route('admin.users.index'), 'keywords' => 'suspend ban user sanction'],
            ['label' => 'Upgrade Quest to featured', 'description' => 'Open Quest Engine and apply a Boost Package.', 'href' => route('admin.quests.index', ['quick' => 'featured']), 'keywords' => 'feature boost quest promote'],
            ['label' => 'Create internal task', 'description' => 'Assign work to an admin teammate.', 'href' => route('admin.tasks.index'), 'keywords' => 'task assign todo work'],
            ['label' => 'Open payout failures', 'description' => 'Review payout reliability and failed payment records.', 'href' => route('admin.financial.index', ['tab' => 'payouts']), 'keywords' => 'payout failure payment'],
            ['label' => 'Create data request', 'description' => 'Process NDPR export or deletion requests.', 'href' => route('admin.compliance.index'), 'keywords' => 'ndpr compliance export deletion data'],
        ])->filter(fn (array $action) => $q === '' || str_contains(Str::lower($action['keywords'].' '.$action['label']), Str::lower($q)))
            ->take(6)
            ->values();

        if ($q === '') {
            return ['actions' => $actions, 'results' => []];
        }

        $users = User::query()
            ->where(fn (Builder $query) => $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
            ->limit(6)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['type' => 'User', 'label' => $user->name, 'description' => $user->email, 'href' => route('admin.users.index', ['q' => $user->email])]);

        $quests = Quest::query()
            ->where(fn (Builder $query) => $query->where('title', 'like', "%{$q}%")->orWhere('reference_code', 'like', "%{$q}%"))
            ->limit(6)
            ->get(['id', 'title', 'reference_code'])
            ->map(fn (Quest $quest) => ['type' => 'Quest', 'label' => $quest->title, 'description' => $quest->reference_code, 'href' => route('admin.quests.index', ['q' => $quest->reference_code])]);

        $disputes = QuestDispute::query()
            ->with('quest:id,title,reference_code')
            ->where('uuid', 'like', "%{$q}%")
            ->orWhereHas('quest', fn (Builder $quest) => $quest->where('title', 'like', "%{$q}%")->orWhere('reference_code', 'like', "%{$q}%"))
            ->limit(4)
            ->get()
            ->map(fn (QuestDispute $dispute) => ['type' => 'Dispute', 'label' => $dispute->quest?->title ?? $dispute->uuid, 'description' => $dispute->status?->value ?? (string) $dispute->status, 'href' => route('admin.disputes.index', ['q' => $dispute->uuid])]);

        $ledger = AdminFinancialLedgerEntry::query()
            ->where('reference', 'like', "%{$q}%")
            ->orWhere('paystack_reference', 'like', "%{$q}%")
            ->limit(4)
            ->get(['reference', 'description'])
            ->map(fn (AdminFinancialLedgerEntry $entry) => ['type' => 'Transaction', 'label' => $entry->reference, 'description' => $entry->description, 'href' => route('admin.financial.index', ['q' => $entry->reference])]);

        $contracts = QuestContract::query()
            ->where('reference_code', 'like', "%{$q}%")
            ->limit(4)
            ->get(['reference_code', 'status'])
            ->map(fn (QuestContract $contract) => [
                'type' => 'Contract',
                'label' => $contract->reference_code,
                'description' => $contract->status->label(),
                'href' => route('admin.contracts.view', $contract->reference_code),
            ]);

        return ['actions' => $actions, 'results' => $users->concat($quests)->concat($disputes)->concat($ledger)->concat($contracts)->values()];
    }

    public function notificationPayload(?User $admin = null): array
    {
        $this->seedCriticalNotifications();
        $query = AdminNotification::query()
            ->where(fn (Builder $q) => $q->whereNull('admin_user_id')->orWhere('admin_user_id', $admin?->id))
            ->where(fn (Builder $q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()))
            ->latest();

        $items = $query->limit(80)->get();

        return [
            'summary' => [
                'unread' => $items->whereNull('read_at')->count(),
                'critical' => $items->where('priority', 'critical')->whereNull('actioned_at')->count(),
                'snoozed' => AdminNotification::query()->where('snoozed_until', '>', now())->count(),
            ],
            'critical_alerts' => $items->where('priority', 'critical')->whereNull('actioned_at')->values()->map(fn (AdminNotification $n) => $this->notificationRow($n, $admin)),
            'items' => $items->map(fn (AdminNotification $n) => $this->notificationRow($n, $admin))->values(),
            'preferences' => collect(['disputes', 'payments', 'verifications', 'flags', 'security', 'system', 'sla'])->map(fn ($category) => [
                'category' => $category,
                'in_app' => true,
                'email' => in_array($category, ['disputes', 'payments', 'security', 'sla'], true),
                'sms' => in_array($category, ['payments', 'security'], true),
            ])->values(),
        ];
    }

    public function taskPayload(?User $admin = null): array
    {
        $tasks = AdminTask::query()
            ->with(['creator:id,name,email', 'assignee:id,name,email'])
            ->latest()
            ->limit(120)
            ->get();

        return [
            'summary' => [
                'mine' => $tasks->where('assigned_to_admin_id', $admin?->id)->where('status', '!=', 'done')->count(),
                'overdue' => $tasks->filter(fn (AdminTask $task) => $task->due_at && $task->due_at->isPast() && $task->status !== 'done')->count(),
                'team_open' => $tasks->where('status', '!=', 'done')->count(),
            ],
            'columns' => collect(['todo' => 'To Do', 'in_progress' => 'In Progress', 'done' => 'Done'])->map(fn ($label, $status) => [
                'status' => $status,
                'label' => $label,
                'items' => $tasks->where('status', $status)->values()->map(fn (AdminTask $task) => $this->taskRow($task)),
            ])->values(),
            'list' => $tasks->map(fn (AdminTask $task) => $this->taskRow($task))->values(),
            'admins' => User::query()->whereHas('role', fn (Builder $q) => $q->whereIn('slug', ['admin', 'super_admin']))->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    public function treasuryPayload(): array
    {
        $earnedFees = (int) AdminFinancialLedgerEntry::query()->whereIn('type', ['platform_fee', 'service_fee', 'featured_listing_payment'])->sum('fee_amount_minor');
        $pendingFees = (int) Quest::query()->whereIn('status', ['assigned', 'in_progress'])->sum(DB::raw('round(coalesce(budget_amount_minor, 0) * 0.1)'));
        $disbursed = (int) AdminFinancialLedgerEntry::query()->where('type', 'payout')->where('status', 'posted')->sum('gross_amount_minor');
        $projected30 = (int) Quest::query()->whereBetween('due_at', [now(), now()->addDays(30)])->sum(DB::raw('round(coalesce(budget_amount_minor, 0) * 0.1)'));

        return [
            'tiles' => [
                ['label' => 'Earned fee balance', 'value' => $this->money($earnedFees), 'empty' => $earnedFees === 0 ? '0 / No earned platform fees have been recorded yet.' : null],
                ['label' => 'Pending fee collections', 'value' => $this->money($pendingFees), 'empty' => $pendingFees === 0 ? '0 / No active contracts are projected to produce fees yet.' : null],
                ['label' => 'Total disbursed payouts', 'value' => $this->money($disbursed), 'empty' => $disbursed === 0 ? '0 / No freelancer payouts have been posted yet.' : null],
                ['label' => '30-day cash projection', 'value' => $this->money($projected30), 'empty' => $projected30 === 0 ? '0 / No expected completions in the next 30 days.' : null],
            ],
            'health' => $earnedFees + $pendingFees > $disbursed ? 'Healthy' : 'Watch',
            'paystack_balance' => ['value' => 'Not synced', 'note' => 'Connect Paystack balance API keys to show live bank balance.'],
        ];
    }

    public function fraudPayload(): array
    {
        $this->seedRiskRules();
        $cases = AdminFraudCase::query()->with(['user:id,name,email', 'assignee:id,name,email'])->latest()->limit(60)->get();
        $riskUsers = User::query()
            ->withCount([
                'questsAsClient as client_disputes_count' => fn (Builder $q) => $q->whereHas('disputes'),
                'questOffers as proposals_count',
            ])
            ->limit(60)
            ->get(['id', 'name', 'email', 'avatar_url'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'risk_score' => min(100, ((int) $user->client_disputes_count * 20) + ((int) $user->proposals_count > 20 ? 15 : 0) + ($user->banned_at ? 40 : 0)),
                'signals' => array_values(array_filter([
                    $user->client_disputes_count ? $user->client_disputes_count.' linked disputes' : null,
                    $user->proposals_count > 20 ? 'High proposal velocity' : null,
                    $user->banned_at ? 'Account banned' : null,
                ])),
            ])
            ->sortByDesc('risk_score')
            ->take(20)
            ->values();

        return [
            'alerts' => $cases->map(fn (AdminFraudCase $case) => [
                'case_number' => $case->case_number,
                'risk_type' => $case->risk_type,
                'risk_score' => $case->risk_score,
                'status' => $case->status,
                'summary' => $case->summary,
                'user' => $case->user?->name,
            ]),
            'risk_leaderboard' => $riskUsers,
            'network' => [
                'nodes' => $riskUsers->take(8)->map(fn ($user) => ['id' => 'user-'.$user['id'], 'label' => $user['name'], 'risk' => $user['risk_score']])->values(),
                'edges' => $riskUsers->take(8)->values()->map(fn ($user, $index) => ['from' => 'user-'.$user['id'], 'to' => 'signal-'.($index % 3), 'label' => $user['signals'][0] ?? 'Shared risk pattern'])->values(),
            ],
            'rules' => AdminRiskRule::query()->latest()->get()->map(fn (AdminRiskRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'category' => $rule->category,
                'severity' => $rule->severity,
                'is_active' => $rule->is_active,
                'description' => $rule->description,
            ]),
        ];
    }

    public function compliancePayload(): array
    {
        $requests = AdminComplianceRequest::query()->with(['user:id,name,email', 'assignee:id,name,email'])->latest()->limit(80)->get();

        return [
            'summary' => [
                'open' => $requests->where('status', 'open')->count(),
                'due_soon' => $requests->filter(fn (AdminComplianceRequest $request) => $request->due_at && $request->due_at->between(now(), now()->addDays(7)))->count(),
                'completed' => $requests->where('status', 'completed')->count(),
            ],
            'requests' => $requests->map(fn (AdminComplianceRequest $request) => [
                'id' => $request->id,
                'reference' => $request->reference,
                'type' => $request->request_type,
                'status' => $request->status,
                'user' => $request->user?->name,
                'email' => $request->user?->email,
                'assignee' => $request->assignee?->name,
                'due_at' => $request->due_at?->toIso8601String(),
                'note' => $request->requester_note,
            ]),
            'retention' => [
                ['data' => 'Notifications and activity logs', 'period' => '24 months', 'action' => 'Archive then purge'],
                ['data' => 'Quest contracts and escrow ledgers', 'period' => '7 years', 'action' => 'Retain for financial audit'],
                ['data' => 'KYC documents', 'period' => '5 years after closure', 'action' => 'Encrypted retention'],
                ['data' => 'Support notes', 'period' => '36 months', 'action' => 'Review before deletion'],
            ],
            'users' => User::query()->orderBy('name')->limit(250)->get(['id', 'name', 'email']),
        ];
    }

    public function intelligencePayload(): array
    {
        $freelancers = User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'freelancer'))
            ->with('trustMetrics')
            ->limit(20)
            ->get()
            ->map(fn (User $user) => $this->freelancerIntelligence($user));
        $clients = User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', 'client'))
            ->with('trustMetrics')
            ->limit(20)
            ->get()
            ->map(fn (User $user) => $this->clientIntelligence($user));

        return [
            'freelancers' => $freelancers,
            'clients' => $clients,
        ];
    }

    private function seedCriticalNotifications(): void
    {
        if (! Schema::hasTable('admin_notifications') || AdminNotification::query()->exists()) {
            return;
        }

        $highDispute = QuestDispute::query()->where('disputed_amount_minor', '>=', 50000000)->latest()->first();
        if ($highDispute) {
            AdminNotification::query()->create([
                'category' => 'disputes',
                'priority' => 'critical',
                'title' => 'High-value dispute requires action',
                'body' => 'A dispute above ₦500,000 is open and must be reviewed.',
                'action_label' => 'Open disputes',
                'action_url' => route('admin.disputes.index'),
            ]);
        }
    }

    private function seedRiskRules(): void
    {
        if (! Schema::hasTable('admin_risk_rules') || AdminRiskRule::query()->exists()) {
            return;
        }

        foreach ([
            ['High dispute velocity', 'account', 80, 'Flags accounts linked to multiple disputes in a short period.'],
            ['Off-platform solicitation', 'content', 70, 'Flags contact sharing or payment diversion patterns.'],
            ['Payout failure cluster', 'payments', 75, 'Flags repeated payout failures for one user or bank.'],
        ] as [$name, $category, $severity, $description]) {
            AdminRiskRule::query()->create(compact('name', 'category', 'severity', 'description') + ['conditions' => []]);
        }
    }

    private function notificationRow(AdminNotification $notification, ?User $viewer): array
    {
        $actionUrl = $viewer
            ? $this->notificationCentre->resolvedActionUrl($notification, $viewer)
            : $notification->action_url;

        return [
            'id' => $notification->id,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_label' => $notification->action_label,
            'action_url' => $actionUrl,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function taskRow(AdminTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'assignee' => $task->assignee?->name,
            'creator' => $task->creator?->name,
            'due_at' => $task->due_at?->toDateString(),
            'overdue' => $task->due_at !== null && $task->due_at->isPast() && $task->status !== 'done',
        ];
    }

    private function freelancerIntelligence(User $user): array
    {
        $contracts = Quest::query()->where('freelancer_id', $user->id);
        $completed = (clone $contracts)->where('status', 'completed')->count();
        $total = max(1, (clone $contracts)->count());
        $earnings = (int) (clone $contracts)->sum('paid_out_minor');
        $proposals = Quest::query()->whereHas('offers', fn (Builder $q) => $q->where('freelancer_id', $user->id))->count();
        $wins = (clone $contracts)->whereNotNull('accepted_quest_offer_id')->count();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'score' => $user->trust_score,
            'earnings' => $this->money($earnings),
            'win_rate' => $proposals > 0 ? round(($wins / $proposals) * 100, 1) : 0,
            'churn_risk' => $user->last_active_at && $user->last_active_at->lt(now()->subDays(30)) ? 'High' : 'Normal',
            'radar' => [
                'quality' => min(100, (int) ($user->avg_rating_as_freelancer * 20 ?: $user->trust_score)),
                'speed' => min(100, max(20, 100 - (int) ($user->response_time_hours ?? 24))),
                'communication' => $user->trust_score,
                'reliability' => round(($completed / $total) * 100),
                'value' => min(100, $earnings > 0 ? 75 : 30),
            ],
        ];
    }

    private function clientIntelligence(User $user): array
    {
        $quests = Quest::query()->where('client_id', $user->id);
        $posted = (clone $quests)->count();
        $funded = (int) (clone $quests)->whereNotNull('escrow_funded_at')->sum('budget_amount_minor');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'posted' => $posted,
            'value' => $this->money($funded),
            'avg_contract' => $this->money($posted > 0 ? (int) ($funded / max(1, $posted)) : 0),
            'rehire_rate' => (clone $quests)->whereNotNull('freelancer_id')->distinct('freelancer_id')->count('freelancer_id'),
            'value_score' => min(100, (int) ($funded / 100000)),
        ];
    }

    private function money(int $minor): string
    {
        return '₦'.number_format($minor / 100, 2);
    }
}
