<?php

namespace App\Http\Controllers;

use App\Enums\AdminProposalStatus;
use App\Enums\PortfolioStatus;
use App\Enums\QuestStatus;
use App\Models\ActivityLog;
use App\Models\LoginEvent;
use App\Models\Portfolio;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\QuestMatchingService;
use App\Services\UserNotificationPresenter;
use App\Support\UserAgentFriendly;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing('role');

        return match ($user->role?->slug) {
            'freelancer' => $this->freelancerHome($user),
            'admin' => redirect()->route('operations.dashboard'),
            'super_admin' => $this->adminHome($user),
            default => $this->clientHome($user),
        };
    }

    protected function freelancerHome(User $user): Response
    {
        $activeStatuses = [
            QuestStatus::Assigned,
            QuestStatus::InProgress,
            QuestStatus::Paused,
            QuestStatus::PendingReview,
            QuestStatus::InDispute,
        ];

        $activeCount = Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->count();

        $completedCount = Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', [QuestStatus::Completed, QuestStatus::Archived])
            ->count();

        $incomeMinor = (int) Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', [QuestStatus::Completed, QuestStatus::Archived])
            ->sum('paid_out_minor');

        $recentQuests = Quest::query()
            ->where('freelancer_id', $user->id)
            ->latest('updated_at')
            ->take(6)
            ->get(['id', 'uuid', 'title', 'status', 'updated_at', 'budget_amount_minor', 'paid_out_minor']);

        $user->loadCount('questCategoryPreferences');
        $incomeBase = fn () => Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', [QuestStatus::Completed, QuestStatus::Archived]);

        return Inertia::render('Home/FreelancerHome', [
            'copy' => [
                'welcome' => __('Welcome back, :name!', ['name' => $user->first_name ?: $user->name]),
                'tagline' => __('Your quests, payouts, and reputation — all in one calm view.'),
            ],
            'incomeCharts' => [
                'six' => $this->monthlyPaidOutMinorSeries($incomeBase, 6),
                'twelve' => $this->monthlyPaidOutMinorSeries($incomeBase, 12),
            ],
            'matchingQuests' => $this->mapMatchingQuestRows($user),
            'recentOffers' => $this->freelancerRecentOffers($user),
            'homeShortcuts' => $this->freelancerHomeShortcuts(),
            'skillCategoriesCount' => (int) ($user->quest_category_preferences_count ?? 0),
            'scoreOpportunities' => $this->freelancerScoreOpportunities($user),
            'trustGuideUrl' => route('dashboard.trust-guide'),
            'stats' => [
                [
                    'label' => __('Active quests'),
                    'value' => (string) $activeCount,
                    'hint' => __('Jobs you are delivering on right now.'),
                    'href' => route('dashboard.lists.show', ['list' => 'freelancer-active-quests']),
                    'icon' => 'briefcase',
                ],
                [
                    'label' => __('Completed'),
                    'value' => (string) $completedCount,
                    'hint' => __('Successfully wrapped milestones.'),
                    'href' => route('dashboard.lists.show', ['list' => 'freelancer-completed-quests']),
                    'icon' => 'check',
                ],
                [
                    'label' => __('Income (paid out)'),
                    'value' => $this->formatNgnFromMinor($incomeMinor),
                    'hint' => __('Total released to you via escrow (completed quests).'),
                    'href' => route('dashboard.lists.show', ['list' => 'freelancer-income-quests']),
                    'icon' => 'banknotes',
                ],
            ],
            'trust' => [
                'freelancer' => $user->trust_score,
                'avg_rating' => $user->avg_rating_as_freelancer,
                'rating_count' => $user->ratings_count_as_freelancer,
                'profile_percent' => $user->profile_completion_percent,
                'explainer' => __('We combine your ratings, on-time delivery, how you handle disputes, profile completeness, and verified documents into one score. It updates as you complete work — scores are never bought or sold, so sponsors can compare fairly.'),
            ],
            'recentQuests' => $recentQuests->map(fn (Quest $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'status' => $q->status->value,
                'updated_at' => $q->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
                'payout_display' => $this->formatNgnFromMinor((int) $q->paid_out_minor),
            ]),
            'portfolioSnapshot' => Portfolio::query()
                ->where('user_id', $user->id)
                ->where('status', PortfolioStatus::Published)
                ->where('admin_hidden', false)
                ->latest('published_at')
                ->take(4)
                ->get()
                ->map(fn (Portfolio $p) => [
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'cover_url' => $p->coverUrl(),
                    'favorites_count' => (int) $p->favorites_count,
                ])
                ->values()
                ->all(),
            ...$this->sharedPanels($user),
        ]);
    }

    protected function clientHome(User $user): Response
    {
        $activeStatuses = [
            QuestStatus::Open,
            QuestStatus::Assigned,
            QuestStatus::InProgress,
            QuestStatus::Paused,
            QuestStatus::PendingReview,
            QuestStatus::InDispute,
        ];

        $activeCount = Quest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->count();

        $spentMinor = (int) Quest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', [
                QuestStatus::Completed,
                QuestStatus::Closed,
                QuestStatus::Archived,
                QuestStatus::PendingReview,
                QuestStatus::InProgress,
                QuestStatus::Assigned,
            ])
            ->sum('paid_out_minor');

        $postedCount = Quest::query()->where('client_id', $user->id)->count();

        $recentQuests = Quest::query()
            ->where('client_id', $user->id)
            ->latest('updated_at')
            ->take(6)
            ->get(['id', 'uuid', 'slug', 'title', 'status', 'updated_at', 'budget_amount_minor', 'paid_out_minor']);

        $spendBase = fn () => Quest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', [
                QuestStatus::Completed,
                QuestStatus::Closed,
                QuestStatus::Archived,
                QuestStatus::PendingReview,
                QuestStatus::InProgress,
                QuestStatus::Assigned,
            ]);

        return Inertia::render('Home/ClientHome', [
            'copy' => [
                'welcome' => __('Good to see you, :name!', ['name' => $user->first_name ?: $user->name]),
                'tagline' => __('Track escrow, approvals, and talent — without the stress.'),
            ],
            'spendCharts' => [
                'six' => $this->monthlyPaidOutMinorSeries($spendBase, 6),
                'twelve' => $this->monthlyPaidOutMinorSeries($spendBase, 12),
            ],
            'attentionQuests' => $this->clientAttentionQuests($user),
            'inboundOffers' => $this->clientInboundOffers($user),
            'homeShortcuts' => $this->clientHomeShortcuts(),
            'scoreOpportunities' => $this->clientScoreOpportunities($user),
            'trustGuideUrl' => route('dashboard.trust-guide'),
            'stats' => [
                [
                    'label' => __('Live quests'),
                    'value' => (string) $activeCount,
                    'hint' => __('Work in motion with your freelancers.'),
                    'href' => route('dashboard.lists.show', ['list' => 'client-live-quests']),
                    'icon' => 'bolt',
                ],
                [
                    'label' => __('Total spent (escrow releases)'),
                    'value' => $this->formatNgnFromMinor($spentMinor),
                    'hint' => __('Funds you have released for delivered milestones.'),
                    'href' => route('dashboard.lists.show', ['list' => 'client-escrow-activity']),
                    'icon' => 'banknotes',
                ],
                [
                    'label' => __('Quests posted'),
                    'value' => (string) $postedCount,
                    'hint' => __('All-time briefs on HustleSafe.'),
                    'href' => route('dashboard.lists.show', ['list' => 'client-all-quests']),
                    'icon' => 'clipboard',
                ],
            ],
            'trust' => [
                'client' => $user->client_trust_score,
                'avg_rating' => $user->avg_rating_as_client,
                'rating_count' => $user->ratings_count_as_client,
                'profile_percent' => $user->profile_completion_percent,
                'explainer' => __('Your client score reflects how clearly you brief work, fund escrow, respond to freelancers, and close quests without unnecessary disputes. It helps talent choose reliable sponsors.'),
            ],
            'recentQuests' => $recentQuests->map(fn (Quest $q) => [
                'id' => $q->id,
                'slug' => $q->slug,
                'uuid' => $q->uuid,
                'title' => $q->title,
                'status' => $q->status->value,
                'updated_at' => $q->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
                'budget_display' => $this->formatNgnFromMinor((int) ($q->budget_amount_minor ?? 0)),
            ]),
            ...$this->sharedPanels($user),
        ]);
    }

    protected function adminHome(User $user): Response
    {
        $userTotal = User::query()->count();
        $freelancers = User::query()->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))->count();
        $clients = User::query()->whereHas('role', fn ($q) => $q->where('slug', 'client'))->count();
        $questsOpen = Quest::query()->whereIn('status', QuestStatus::operationalStatuses())->count();

        $chart = $this->signupSeriesLast7Days();

        return Inertia::render('Home/AdminHome', [
            'copy' => [
                'welcome' => __('Operations desk'),
                'tagline' => __('High-level pulse — super-admin tools ship separately.'),
            ],
            'stats' => [
                [
                    'label' => __('Registered users'),
                    'value' => (string) $userTotal,
                    'hint' => __('Everyone on the platform.'),
                ],
                [
                    'label' => __('Freelancers'),
                    'value' => (string) $freelancers,
                    'hint' => __('Safe Hustlers'),
                ],
                [
                    'label' => __('Clients'),
                    'value' => (string) $clients,
                    'hint' => __('Project sponsors'),
                ],
                [
                    'label' => __('Active quests'),
                    'value' => (string) $questsOpen,
                    'hint' => __('Open through in-progress.'),
                ],
            ],
            'chart' => $chart,
            ...$this->sharedPanels($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedPanels(User $user): array
    {
        $recentLogins = LoginEvent::query()
            ->where('user_id', $user->id)
            ->latest('logged_in_at')
            ->take(20)
            ->get(['logged_in_at', 'ip_address', 'user_agent'])
            ->unique(fn (LoginEvent $e) => $e->logged_in_at?->format('Y-m-d H:i').'|'.($e->ip_address ?? '').'|'.UserAgentFriendly::label($e->user_agent))
            ->take(5)
            ->values();

        $activities = ActivityLog::query()
            ->where(function ($q) use ($user) {
                $q->where('subject_user_id', $user->id)
                    ->orWhere('actor_id', $user->id);
            })
            ->latest('created_at')
            ->take(10)
            ->get(['title', 'body', 'type', 'created_at', 'meta']);

        $notifications = collect(app(UserNotificationPresenter::class)->recentForNav($user, 10))
            ->map(fn (array $r) => [
                'id' => $r['id'],
                'read' => $r['read'],
                'label' => $r['label'],
                'href' => $r['href'] ?? null,
                'data' => $r['data'] ?? [],
                'created_at' => $r['created_at'] ?? null,
            ])
            ->values();

        return [
            'recentLogins' => $recentLogins->map(fn (LoginEvent $e) => [
                'at' => $e->logged_in_at->timezone('Africa/Lagos')->toIso8601String(),
                'ip' => $e->ip_address,
                'device' => UserAgentFriendly::label($e->user_agent),
            ]),
            'activities' => $activities->map(fn (ActivityLog $a) => [
                'title' => $a->title,
                'body' => $a->body,
                'type' => $a->type,
                'created_at' => $a->created_at->timezone('Africa/Lagos')->toIso8601String(),
            ]),
            'notifications' => $notifications,
        ];
    }

    /**
     * @return array{labels: string[], values: int[], peak: int}
     */
    protected function signupSeriesLast7Days(): array
    {
        $start = Carbon::now('Africa/Lagos')->subDays(6)->startOfDay();

        $rows = DB::table('users')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now('Africa/Lagos')->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($day)->format('D j');
            $values[] = (int) ($rows[$day] ?? 0);
        }

        $peak = max($values) ?: 1;

        return compact('labels', 'values', 'peak');
    }

    /**
     * @param  callable(): Builder<Quest>  $baseQuery
     * @return array{labels: string[], values: int[], peak: int}
     */
    protected function monthlyPaidOutMinorSeries(callable $baseQuery, int $months): array
    {
        $labels = [];
        $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $anchor = Carbon::now('Africa/Lagos')->subMonths($i);
            $labels[] = $anchor->format('M');
            $start = $anchor->copy()->startOfMonth()->utc();
            $end = $anchor->copy()->endOfMonth()->utc();
            $sum = (int) $baseQuery()->whereBetween('updated_at', [$start, $end])->sum('paid_out_minor');
            $values[] = $sum;
        }

        $peak = max($values) ?: 1;

        return [
            'labels' => $labels,
            'values' => $values,
            'peak' => $peak,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapMatchingQuestRows(User $user): array
    {
        return app(QuestMatchingService::class)
            ->rankedOpenQuestsForFreelancer($user, 6)
            ->map(function (array $row) {
                $q = $row['quest'];

                return [
                    'id' => $q->id,
                    'uuid' => $q->uuid,
                    'title' => $q->title,
                    'match_score' => $row['match_score'],
                    'reasons' => array_slice($row['reasons'], 0, 2),
                    'budget_display' => $this->formatNgnFromMinor((int) ($q->budget_amount_minor ?? 0)),
                    'category' => $q->questCategory?->name,
                    'state' => $q->stateModel?->name,
                    'city' => $q->city,
                    'posted_at' => $q->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function freelancerRecentOffers(User $user): array
    {
        return QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->excludingAdminSuspended()
            ->with(['quest:id,uuid,title,status'])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (QuestOffer $o) => [
                'id' => $o->id,
                'status' => $o->status,
                'pitch_preview' => $o->pitch !== null && $o->pitch !== '' ? (string) Str::limit((string) $o->pitch, 100) : null,
                'quest_title' => $o->quest?->title,
                'quest_status' => $o->quest?->status?->value,
                'updated_at' => $o->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function clientInboundOffers(User $user): array
    {
        return QuestOffer::query()
            ->whereHas('quest', fn ($q) => $q->where('client_id', $user->id))
            ->visibleInClientInbox()
            ->with(['quest:id,uuid,slug,title,status', 'freelancer:id,first_name,name,avatar_url'])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (QuestOffer $o) => [
                'id' => $o->id,
                'status' => $o->status,
                'freelancer_label' => $o->freelancer?->first_name ?: $o->freelancer?->name,
                'quest_title' => $o->quest?->title,
                'updated_at' => $o->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
                'proposal_url' => $o->quest ? route('quests.proposals.show', [$o->quest->getRouteKey(), $o->id]) : null,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function clientAttentionQuests(User $user): array
    {
        return Quest::query()
            ->where('client_id', $user->id)
            ->whereIn('status', [
                QuestStatus::PendingReview,
                QuestStatus::InDispute,
                QuestStatus::Paused,
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get(['id', 'uuid', 'slug', 'title', 'status', 'updated_at', 'budget_amount_minor'])
            ->map(fn (Quest $q) => [
                'id' => $q->id,
                'slug' => $q->slug,
                'uuid' => $q->uuid,
                'title' => $q->title,
                'status' => $q->status->value,
                'updated_at' => $q->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
                'budget_display' => $this->formatNgnFromMinor((int) ($q->budget_amount_minor ?? 0)),
            ])
            ->all();
    }

    /**
     * @return list<array{label: string, description: string, href: string, icon: string}>
     */
    protected function freelancerHomeShortcuts(): array
    {
        return [
            [
                'label' => __('Your portfolio'),
                'description' => __('Showcase completed work — drafts stay private until you publish.'),
                'href' => route('portfolio.manage'),
                'icon' => 'photo',
            ],
            [
                'label' => __('Explore quests & send proposals'),
                'description' => __('Open briefs matched to your skills and location.'),
                'href' => route('quests.explore'),
                'icon' => 'search',
            ],
            [
                'label' => __('Improve your trust score'),
                'description' => __('ID, profile, delivery habits — what actually moves the needle.'),
                'href' => route('dashboard.trust-guide'),
                'icon' => 'shield',
            ],
            [
                'label' => __('Categories & subcategories'),
                'description' => __('Fine-tune what you want to be hired for.'),
                'href' => route('account.security.edit'),
                'icon' => 'squares',
            ],
            [
                'label' => __('Reviews after delivery'),
                'description' => __('Completed quests — follow up and leave fair feedback.'),
                'href' => route('dashboard.lists.show', ['list' => 'freelancer-completed-quests']),
                'icon' => 'star',
            ],
        ];
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    protected function freelancerScoreOpportunities(User $user): array
    {
        $user->loadCount('questCategoryPreferences');
        $out = [];

        if (($user->quest_category_preferences_count ?? 0) < 2) {
            $out[] = [
                'label' => __('Add more quest categories'),
                'href' => route('account.security.edit'),
            ];
        }

        if (($user->profile_completion_percent ?? 0) < 85) {
            $out[] = [
                'label' => __('Complete your profile'),
                'href' => route('account.security.edit'),
            ];
        }

        $out[] = [
            'label' => __('Verify ID & documents'),
            'href' => route('verifications.index'),
        ];

        return array_slice($out, 0, 3);
    }

    /**
     * @return list<array{label: string, description: string, href: string, icon: string}>
     */
    protected function clientHomeShortcuts(): array
    {
        return [
            [
                'label' => __('Create a new quest'),
                'description' => __('Brief verified talent with escrow-ready milestones.'),
                'href' => route('quests.create'),
                'icon' => 'plus',
            ],
            [
                'label' => __('View proposals on your quests'),
                'description' => __('Compare pitches before you assign someone.'),
                'href' => route('dashboard.lists.show', ['list' => 'client-proposals-inbox']),
                'icon' => 'inbox',
            ],
            [
                'label' => __('Verifications & trust'),
                'description' => __('Organisation checks that speed up hiring.'),
                'href' => route('verifications.index'),
                'icon' => 'shield',
            ],
            [
                'label' => __('Account & billing contacts'),
                'description' => __('Keep approvals and notifications routed correctly.'),
                'href' => route('account.security.edit'),
                'icon' => 'cog',
            ],
        ];
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    protected function clientScoreOpportunities(User $user): array
    {
        $out = [];

        if (($user->profile_completion_percent ?? 0) < 85) {
            $out[] = [
                'label' => __('Complete sponsor profile'),
                'href' => route('account.security.edit'),
            ];
        }

        $out[] = [
            'label' => __('Keep verifications current'),
            'href' => route('verifications.index'),
        ];

        $out[] = [
            'label' => __('Post clear quest briefs'),
            'href' => route('quests.create'),
        ];

        return array_slice($out, 0, 3);
    }

    protected function formatNgnFromMinor(int $minorUnits): string
    {
        $naira = $minorUnits / 100;

        return '₦'.number_format($naira, 0);
    }
}
