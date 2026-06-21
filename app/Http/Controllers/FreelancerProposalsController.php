<?php

namespace App\Http\Controllers;

use App\Enums\QuestStatus;
use App\Models\QuestOffer;
use App\Support\ProposalMoneyCalculator;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FreelancerProposalsController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if ($user === null || $user->role?->slug !== 'freelancer') {
            return redirect()->route('dashboard');
        }

        $offers = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->excludingAdminSuspended()
            ->with([
                'quest:id,uuid,slug,title,status,quest_category_id,budget_amount_minor,city,state_id',
                'quest.questCategory:id,name,parent_id',
                'quest.questCategory.parent:id,name',
                'quest.stateModel:id,name',
            ])
            ->latest('created_at')
            ->limit(500)
            ->get();

        $proposals = $offers
            ->map(fn (QuestOffer $offer) => $this->serializeProposal($offer))
            ->values()
            ->all();

        $stats = $this->buildStats($offers);

        return Inertia::render('Freelancer/Proposals/Index', [
            'proposals' => $proposals,
            'stats' => $stats,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeProposal(QuestOffer $offer): array
    {
        $quest = $offer->quest;
        $routeKey = $quest ? ($quest->slug ?: $quest->uuid) : null;
        $category = $quest?->questCategory;
        $parent = $category?->parent;
        $quotedMinor = ProposalMoneyCalculator::quoteTotalMinor($offer->pricing_snapshot ?? []);
        $finish = $offer->planned_finish_date?->toDateString()
            ?? $offer->proposed_completion_date?->toDateString();

        return [
            'id' => $offer->id,
            'reference_code' => $offer->reference_code,
            'route_key' => $offer->getRouteKey(),
            'status' => $offer->status,
            'pitch_preview' => $offer->pitch !== null && $offer->pitch !== ''
                ? (string) Str::limit(strip_tags((string) $offer->pitch), 160)
                : null,
            'quoted_amount_minor' => $quotedMinor,
            'submitted_at' => $offer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'updated_at' => $offer->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'client_view_count' => (int) ($offer->client_view_count ?? 0),
            'last_client_view_at' => $offer->last_client_view_at?->timezone('Africa/Lagos')->toIso8601String(),
            'shortlisted_at' => $offer->shortlisted_at?->timezone('Africa/Lagos')->toIso8601String(),
            'can_edit' => $offer->freelancer_edit_deadline_at !== null
                && now()->lessThanOrEqualTo($offer->freelancer_edit_deadline_at)
                && in_array($offer->status, ['submitted', 'shortlisted'], true),
            'timeline_label' => $finish,
            'show_url' => $routeKey ? route('quests.proposals.show', [$routeKey, $offer]) : null,
            'edit_url' => $routeKey && $offer->freelancer_edit_deadline_at !== null
                && now()->lessThanOrEqualTo($offer->freelancer_edit_deadline_at)
                && in_array($offer->status, ['submitted', 'shortlisted'], true)
                ? route('quests.proposals.edit', [$routeKey, $offer])
                : null,
            'quest' => $quest ? [
                'id' => $quest->id,
                'route_key' => $routeKey,
                'title' => $quest->title,
                'status' => $quest->status instanceof QuestStatus ? $quest->status->value : (string) $quest->status,
                'cover_url' => $quest->displayCoverUrl(),
                'budget_minor' => (int) ($quest->budget_amount_minor ?? 0),
                'category' => $category?->name,
                'parent_category' => $parent?->name,
                'location' => collect([$quest->city, $quest->stateModel?->name])->filter()->implode(' · '),
                'show_url' => $routeKey ? route('quests.show', $routeKey) : null,
            ] : null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, QuestOffer>  $offers
     * @return array<string, int>
     */
    protected function buildStats($offers): array
    {
        $activeStatuses = ['submitted', 'shortlisted', 'pending_award'];

        return [
            'total' => $offers->count(),
            'active' => $offers->whereIn('status', $activeStatuses)->count(),
            'submitted' => $offers->where('status', 'submitted')->count(),
            'shortlisted' => $offers->where('status', 'shortlisted')->count(),
            'pending_award' => $offers->where('status', 'pending_award')->count(),
            'accepted' => $offers->where('status', 'accepted')->count(),
            'declined' => $offers->where('status', 'declined')->count(),
            'withdrawn' => $offers->where('status', 'withdrawn')->count(),
        ];
    }
}
