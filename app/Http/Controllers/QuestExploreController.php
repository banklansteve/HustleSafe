<?php

namespace App\Http\Controllers;

use App\Models\QuestBoost;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\QuestMatchingService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestExploreController extends Controller
{
    public function __invoke(Request $request, FreelancerWorkspaceReadinessService $workspace): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role', 'questCategoryPreferences']);

        if (! in_array($user->role?->slug, ['freelancer', 'client'], true)) {
            return redirect()->route('dashboard');
        }

        $rows = app(QuestMatchingService::class)->discoveryFeedForExplore($user, 48);

        $freelancerOffers = $user->role?->slug === 'freelancer'
            ? QuestOffer::mapForFreelancerOnQuests(
                (int) $user->id,
                $rows->map(fn (array $row) => (int) $row['quest']->id)->all(),
            )
            : [];

        $verificationEngine = $user->role?->slug === 'freelancer'
            ? app(VerificationEngineService::class)
            : null;

        $quests = $rows->map(function (array $row) use ($user, $workspace, $freelancerOffers, $verificationEngine) {
            $q = $row['quest'];
            $cat = $q->questCategory;
            $parent = $cat?->parent;
            $myOffer = $freelancerOffers[$q->id] ?? null;
            $budgetMinor = (int) ($q->budget_amount_minor ?? 0);
            $categoryMatch = $user->role?->slug === 'freelancer' && $workspace->matchesQuestCategory($user, $q);

            return [
                'id' => $q->id,
                'uuid' => $q->uuid,
                'slug' => $q->slug,
                'title' => $q->title,
                'match_score' => $row['match_score'],
                'match_quality' => $row['match_quality'] ?? ['label' => '', 'stars' => 0],
                'match_breakdown' => $row['match_breakdown'] ?? [],
                'location_tier' => $row['location_tier'] ?? 'unknown',
                'reasons' => array_slice($row['reasons'], 0, 3),
                'budget_minor' => $budgetMinor,
                'cover_url' => $q->displayCoverUrl(),
                'category' => $cat?->name,
                'parent_category' => $parent?->name,
                'state' => $q->stateModel?->name,
                'city' => $q->city,
                'posted_at' => $q->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'is_boosted' => QuestBoost::query()->where('quest_id', $q->id)->activeNow()->exists(),
                'category_match' => $categoryMatch,
                'budget_within_limit' => $verificationEngine === null
                    || $verificationEngine->freelancerCanProposeForBudget($user, $budgetMinor),
                'has_my_proposal' => $myOffer !== null,
                'my_proposal' => $myOffer ? [
                    'id' => $myOffer->id,
                    'status' => $myOffer->status,
                    'show_url' => route('quests.proposals.show', [$q, $myOffer]),
                    'submitted_at' => $myOffer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ] : null,
            ];
        })->values()->all();

        $freelancerProposals = [];
        foreach ($freelancerOffers as $questId => $offer) {
            $row = $rows->first(fn (array $item) => (int) $item['quest']->id === (int) $questId);
            $quest = $row['quest'] ?? null;
            if ($quest === null) {
                continue;
            }
            $freelancerProposals[(int) $questId] = [
                'id' => $offer->id,
                'status' => $offer->status,
                'show_url' => route('quests.proposals.show', [$quest, $offer]),
                'submitted_at' => $offer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ];
        }

        return Inertia::render('Quests/Explore', [
            'quests' => $quests,
            'freelancer_proposals' => $freelancerProposals,
            'workspace' => $workspace->toInertiaProps($user),
            'explore_mode' => $user->role?->slug === 'client' ? 'client' : 'freelancer',
            'verification_access' => $verificationEngine ? [
                'effective_level' => $verificationEngine->effectiveLevel($user),
                'proposal_limit_minor' => $verificationEngine->freelancerProposalLimitMinor($user),
                'missing_for_next_level' => $verificationEngine->missingForNextLevelPublic($user),
                'verifications_url' => route('verifications.index'),
            ] : null,
        ]);
    }
}
