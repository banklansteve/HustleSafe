<?php

namespace App\Http\Controllers;

use App\Enums\AdminProposalStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardListController extends Controller
{
    /** @var list<string> */
    protected const FREELANCER_LISTS = [
        'freelancer-active-quests',
        'freelancer-completed-quests',
        'freelancer-income-quests',
        'freelancer-proposals-sent',
    ];

    /** @var list<string> */
    protected const CLIENT_LISTS = [
        'client-live-quests',
        'client-all-quests',
        'client-escrow-activity',
        'client-proposals-inbox',
    ];

    protected function canonicalListKey(string $list): string
    {
        return match ($list) {
            'freelancer-offers-sent' => 'freelancer-proposals-sent',
            'client-offers-inbox' => 'client-proposals-inbox',
            default => $list,
        };
    }

    public function show(Request $request, string $list): Response|JsonResponse
    {
        $list = $this->canonicalListKey($list);
        $user = $request->user();
        $user->loadMissing('role');
        $slug = $user->role?->slug ?? 'client';

        if (! $this->userMayAccessList($slug, $list)) {
            throw new NotFoundHttpException;
        }

        $meta = $this->metaForList($list);
        if ($meta === null) {
            throw new NotFoundHttpException;
        }

        $query = $this->queryForList($list, $user);
        $paginator = $query->paginate(12)->withQueryString();

        $items = collect($paginator->items())->map(fn ($row) => $this->serializeRow($list, $row))->values()->all();

        if ($request->wantsJson()) {
            return response()->json([
                'items' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ]);
        }

        return Inertia::render('Dashboard/ListView', [
            'listKey' => $list,
            'title' => $meta['title'],
            'subtitle' => $meta['subtitle'],
            'emptyMessage' => $meta['empty'],
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    protected function userMayAccessList(string $roleSlug, string $list): bool
    {
        if ($roleSlug === 'freelancer') {
            return in_array($list, self::FREELANCER_LISTS, true);
        }

        if (in_array($roleSlug, ['admin', 'super_admin'], true)) {
            return false;
        }

        return in_array($list, self::CLIENT_LISTS, true);
    }

    /**
     * @return array{title: string, subtitle: string, empty: string}|null
     */
    protected function metaForList(string $list): ?array
    {
        return match ($list) {
            'freelancer-active-quests' => [
                'title' => __('Active quests'),
                'subtitle' => __('Work you are delivering right now — statuses update as milestones move.'),
                'empty' => __('You have no active quests yet. Explore open briefs and send a proposal.'),
            ],
            'freelancer-completed-quests' => [
                'title' => __('Completed quests'),
                'subtitle' => __('Wrapped jobs — great place to follow up for reviews.'),
                'empty' => __('No completed quests yet — your finished work will show here.'),
            ],
            'freelancer-income-quests' => [
                'title' => __('Income & payouts'),
                'subtitle' => __('Escrow releases recorded on completed or archived quests.'),
                'empty' => __('No payout history yet — completed quests with releases appear here.'),
            ],
            'freelancer-proposals-sent' => [
                'title' => __('Proposals you sent'),
                'subtitle' => __('Pitches and quotes you have shared on open quests.'),
                'empty' => __('You have not sent proposals yet — browse matched quests to pitch sponsors.'),
            ],
            'client-live-quests' => [
                'title' => __('Live quests'),
                'subtitle' => __('Open, assigned, in progress, review, pause, or dispute.'),
                'empty' => __('No live quests — post a quest to hire verified talent.'),
            ],
            'client-all-quests' => [
                'title' => __('All your quests'),
                'subtitle' => __('Every brief you have posted on HustleSafe.'),
                'empty' => __('You have not posted a quest yet.'),
            ],
            'client-escrow-activity' => [
                'title' => __('Escrow releases'),
                'subtitle' => __('Quests where funds have moved through escrow milestones.'),
                'empty' => __('No escrow activity yet — releases appear as freelancers deliver.'),
            ],
            'client-proposals-inbox' => [
                'title' => __('Proposals on your quests'),
                'subtitle' => __('Freelancer responses waiting for your review.'),
                'empty' => __('No proposals yet — when freelancers pitch, they appear here.'),
            ],
            default => null,
        };
    }

    /**
     * @return Builder<Quest|QuestOffer>
     */
    protected function queryForList(string $list, User $user)
    {
        $activeFreelancer = [
            QuestStatus::Assigned,
            QuestStatus::InProgress,
            QuestStatus::Paused,
            QuestStatus::PendingReview,
            QuestStatus::InDispute,
        ];

        $clientSpendStatuses = [
            QuestStatus::Completed,
            QuestStatus::Closed,
            QuestStatus::Archived,
            QuestStatus::PendingReview,
            QuestStatus::InProgress,
            QuestStatus::Assigned,
        ];

        $clientLive = [
            QuestStatus::Open,
            QuestStatus::Assigned,
            QuestStatus::InProgress,
            QuestStatus::Paused,
            QuestStatus::PendingReview,
            QuestStatus::InDispute,
        ];

        return match ($list) {
            'freelancer-active-quests' => Quest::query()
                ->where('freelancer_id', $user->id)
                ->whereIn('status', $activeFreelancer)
                ->latest('updated_at'),

            'freelancer-completed-quests' => Quest::query()
                ->where('freelancer_id', $user->id)
                ->whereIn('status', [QuestStatus::Completed, QuestStatus::Archived])
                ->latest('updated_at'),

            'freelancer-income-quests' => Quest::query()
                ->where('freelancer_id', $user->id)
                ->whereIn('status', [QuestStatus::Completed, QuestStatus::Archived])
                ->where('paid_out_minor', '>', 0)
                ->latest('updated_at'),

            'freelancer-proposals-sent' => QuestOffer::query()
                ->where('freelancer_id', $user->id)
                ->when(Schema::hasColumn('quest_offers', 'admin_status'), fn ($query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminProposalStatus::Suspended->value))
                ->with(['quest:id,uuid,slug,title,status'])
                ->latest('updated_at'),

            'client-live-quests' => Quest::query()
                ->where('client_id', $user->id)
                ->whereIn('status', $clientLive)
                ->latest('updated_at'),

            'client-all-quests' => Quest::query()
                ->where('client_id', $user->id)
                ->latest('updated_at'),

            'client-escrow-activity' => Quest::query()
                ->where('client_id', $user->id)
                ->whereIn('status', $clientSpendStatuses)
                ->where('paid_out_minor', '>', 0)
                ->latest('updated_at'),

            'client-proposals-inbox' => QuestOffer::query()
                ->whereHas('quest', fn ($q) => $q->where('client_id', $user->id))
                ->when(Schema::hasColumn('quest_offers', 'admin_status'), fn ($query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminProposalStatus::Suspended->value))
                ->with(['quest:id,uuid,slug,title,status', 'freelancer:id,first_name,name,avatar_url'])
                ->latest('updated_at'),

            default => Quest::query()->whereRaw('1 = 0'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRow(string $list, Quest|QuestOffer $row): array
    {
        if ($row instanceof QuestOffer) {
            $quest = $row->quest;
            $routeKey = $quest ? ($quest->slug ?: $quest->uuid) : null;
            $href = $routeKey ? route('quests.proposals.show', [$routeKey, $row->id]) : null;
            $quotedMinor = (int) ($row->quoted_amount_minor ?? 0);

            return [
                'kind' => 'proposal',
                'id' => $row->id,
                'status' => $row->status,
                'title' => $row->quest?->title,
                'submitted_at' => $row->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'updated_at' => $row->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
                'quest_title' => $row->quest?->title,
                'quest_status' => $row->quest?->status?->value,
                'quest_slug' => $row->quest?->slug,
                'quest_uuid' => $row->quest?->uuid,
                'freelancer_label' => $list === 'client-proposals-inbox'
                    ? ($row->freelancer?->first_name ?: $row->freelancer?->name)
                    : null,
                'freelancer_avatar_url' => $list === 'client-proposals-inbox'
                    ? $row->freelancer?->avatar_url
                    : null,
                'quoted_amount_display' => $this->formatNgnFromMinor($quotedMinor),
                'href' => $href,
            ];
        }

        return [
            'kind' => 'quest',
            'id' => $row->id,
            'uuid' => $row->uuid,
            'slug' => $row->slug,
            'title' => $row->title,
            'status' => $row->status->value,
            'updated_at' => $row->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'paid_out_display' => $this->formatNgnFromMinor((int) ($row->paid_out_minor ?? 0)),
            'budget_display' => $this->formatNgnFromMinor((int) ($row->budget_amount_minor ?? 0)),
        ];
    }

    protected function formatNgnFromMinor(int $minorUnits): string
    {
        $naira = $minorUnits / 100;

        return '₦'.number_format($naira, 0);
    }
}
