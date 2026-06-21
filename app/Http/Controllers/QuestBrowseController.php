<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\BrowseQuestsRequest;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\Quest\QuestBrowseService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuestBrowseController extends Controller
{
    public function __invoke(
        BrowseQuestsRequest $request,
        QuestBrowseService $browse,
        FreelancerWorkspaceReadinessService $workspace,
    ): Response|RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role', 'questCategoryPreferences']);

        if ($user->role?->slug !== 'freelancer') {
            return redirect()->route('dashboard');
        }

        $queryFilters = $request->filters();
        $resolved = $browse->resolveFilters($user, $queryFilters, $request->cleared(), $request->smart());
        $filters = $resolved['filters'];
        $usingSmartDefaults = $resolved['using_smart_defaults'];

        $paginator = $browse->paginate($user, $filters);
        $scored = $browse->scoreQuests($user, $paginator->getCollection());

        if ($filters['sort'] === 'match_desc') {
            $scored = $scored
                ->sortByDesc(fn (array $row) => [
                    $row['match_score'],
                    $row['quest']->created_at?->timestamp ?? 0,
                ])
                ->values();
            $paginator->setCollection($scored->pluck('quest'));
        }

        $scoreByQuestId = $scored->keyBy(fn (array $row) => (int) $row['quest']->id);

        $questIds = $paginator->getCollection()->map(fn (Quest $q) => (int) $q->id)->all();
        $boostedQuestIds = QuestBoost::query()
            ->whereIn('quest_id', $questIds)
            ->activeNow()
            ->pluck('quest_id')
            ->flip();
        $freelancerOffers = QuestOffer::mapForFreelancerOnQuests((int) $user->id, $questIds);
        $verificationEngine = app(VerificationEngineService::class);

        $quests = $paginator->getCollection()->map(function ($q) use ($user, $workspace, $freelancerOffers, $verificationEngine, $scoreByQuestId, $boostedQuestIds) {
            $row = $scoreByQuestId->get((int) $q->id) ?? [];
            $cat = $q->questCategory;
            $parent = $cat?->parent;
            $myOffer = $freelancerOffers[$q->id] ?? null;
            $budgetMinor = (int) ($q->budget_amount_minor ?? 0);

            return [
                'id' => $q->id,
                'uuid' => $q->uuid,
                'slug' => $q->slug,
                'title' => $q->title,
                'match_score' => (int) ($row['match_score'] ?? 0),
                'match_quality' => $row['match_quality'] ?? ['label' => '', 'stars' => 0],
                'match_breakdown' => $row['match_breakdown'] ?? [],
                'location_tier' => $row['location_tier'] ?? 'unknown',
                'reasons' => $row['reasons'] ?? [],
                'budget_minor' => $budgetMinor,
                'cover_url' => $q->displayCoverUrl(),
                'category' => $cat?->name,
                'parent_category' => $parent?->name,
                'state' => $q->stateModel?->name,
                'lga' => $q->localGovernment?->name,
                'city' => $q->city,
                'required_skills' => array_values(array_slice($q->required_skills ?? [], 0, 2)),
                'posted_at' => $q->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'delivery_deadline' => $q->delivery_deadline?->timezone('Africa/Lagos')->toIso8601String(),
                'is_boosted' => $boostedQuestIds->has((int) $q->id),
                'category_match' => $workspace->matchesQuestCategory($user, $q),
                'budget_within_limit' => $verificationEngine->freelancerCanProposeForBudget($user, $budgetMinor),
                'has_my_proposal' => $myOffer !== null,
                'my_proposal' => $myOffer ? [
                    'id' => $myOffer->id,
                    'status' => $myOffer->status,
                    'show_url' => route('quests.proposals.show', [$q, $myOffer]),
                    'submitted_at' => $myOffer->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ] : null,
            ];
        })->values()->all();

        return Inertia::render('Quests/Browse', [
            'quests' => [
                'data' => $quests,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => $paginator->linkCollection()->toArray(),
            ],
            'filters' => array_merge($filters, [
                'using_smart_defaults' => $usingSmartDefaults,
                'cleared' => $request->cleared(),
            ]),
            'filter_options' => [
                'locations' => $browse->locationsPayload(),
                'category_tree' => $browse->categoryTreePayload(),
                'popular_skills' => $browse->popularSkills(),
                'sort_options' => [
                    ['value' => 'posted_desc', 'label' => 'Newest posted'],
                    ['value' => 'posted_asc', 'label' => 'Oldest posted'],
                    ['value' => 'match_desc', 'label' => 'Best match for you'],
                    ['value' => 'budget_desc', 'label' => 'Highest budget'],
                    ['value' => 'budget_asc', 'label' => 'Lowest budget'],
                    ['value' => 'deadline_asc', 'label' => 'Soonest deadline'],
                ],
            ],
            'workspace' => $workspace->toInertiaProps($user),
            'verification_access' => $verificationEngine->freelancerVerificationAccessContext($user),
        ]);
    }
}
