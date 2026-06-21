<?php

namespace App\Services\Quest;

use App\Enums\AdminQuestStatus;
use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\State;
use App\Models\User;
use App\Services\Matching\FreelancerMetricsService;
use App\Services\Matching\QuestMatchScoreCalculator;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QuestBrowseService
{
    public function __construct(
        protected QuestMatchScoreCalculator $scoreCalculator,
        protected FreelancerMetricsService $metricsService,
    ) {}

    /**
     * @param  array<string, mixed>  $queryFilters
     * @return array{filters: array<string, mixed>, using_smart_defaults: bool}
     */
    public function resolveFilters(User $freelancer, array $queryFilters, bool $cleared, bool $smart = false): array
    {
        if ($cleared) {
            return [
                'filters' => $queryFilters,
                'using_smart_defaults' => false,
            ];
        }

        if ($smart || ! $this->hasExplicitFilters($queryFilters)) {
            return [
                'filters' => $this->applySmartDefaults($freelancer, $queryFilters),
                'using_smart_defaults' => true,
            ];
        }

        return [
            'filters' => $queryFilters,
            'using_smart_defaults' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(User $freelancer, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $sort = (string) ($filters['sort'] ?? 'posted_desc');
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = $this->baseQuery();
        $this->applyFilters($query, $filters);
        $this->applySort($query, $sort);

        return $query
            ->with([
                'questCategory:id,parent_id,name',
                'questCategory.parent:id,name',
                'stateModel:id,name',
                'localGovernment:id,name',
                'client:id,first_name,name',
            ])
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @param  Collection<int, Quest>  $quests
     * @return Collection<int, array{quest: Quest, match_score: int, match_quality: array, match_breakdown: list<string>, location_tier: string, reasons: list<string>}>
     */
    public function scoreQuests(User $freelancer, Collection $quests): Collection
    {
        $metrics = $this->metricsService->forUser($freelancer);

        return $quests->map(function (Quest $quest) use ($freelancer, $metrics): array {
            $breakdown = $this->scoreCalculator->score($freelancer, $quest, $metrics);
            $total = min(100, (int) round($breakdown['total']));

            return [
                'quest' => $quest,
                'match_score' => $total,
                'match_quality' => $this->scoreCalculator->qualityForScore($total),
                'match_breakdown' => $breakdown['breakdown_lines'],
                'location_tier' => $breakdown['location_tier'],
                'reasons' => array_slice($breakdown['reasons'], 0, 3),
            ];
        });
    }

    /**
     * @return list<array{id: int, name: string, local_governments: list<array{id: int, name: string}>}>
     */
    public function locationsPayload(): array
    {
        return State::query()
            ->with(['localGovernments:id,state_id,name'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (State $state) => [
                'id' => $state->id,
                'name' => $state->name,
                'local_governments' => $state->localGovernments
                    ->map(fn ($lga) => ['id' => $lga->id, 'name' => $lga->name])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categoryTreePayload(): array
    {
        return QuestCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->where('status', 'active')->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (QuestCategory $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'children' => $parent->children->map(fn (QuestCategory $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function popularSkills(): array
    {
        $skills = collect(config('quest_skill_dictionary.common', []));

        foreach (config('quest_skill_dictionary.by_parent', []) as $list) {
            $skills = $skills->merge((array) $list);
        }

        return $skills
            ->map(fn ($skill) => trim((string) $skill))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->take(40)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $queryFilters
     */
    protected function hasExplicitFilters(array $queryFilters): bool
    {
        foreach (['q', 'state_id', 'local_government_id', 'parent_category_id', 'quest_category_id', 'skill'] as $key) {
            if (! empty($queryFilters[$key])) {
                return true;
            }
        }

        if (! empty($queryFilters['category_ids'])) {
            return true;
        }

        if (! empty($queryFilters['budget_min']) || ! empty($queryFilters['budget_max'])) {
            return true;
        }

        if (! empty($queryFilters['budget_min_ngn']) || ! empty($queryFilters['budget_max_ngn'])) {
            return true;
        }

        if (! empty($queryFilters['sort']) && $queryFilters['sort'] !== 'posted_desc') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $queryFilters
     * @return array<string, mixed>
     */
    protected function applySmartDefaults(User $freelancer, array $queryFilters): array
    {
        $freelancer->loadMissing(['questCategoryPreferences.parent']);
        $verification = app(VerificationEngineService::class);

        $categoryIds = $freelancer->questCategoryPreferences
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $parentCategoryId = null;
        $questCategoryId = null;
        $profileCategoryIds = [];

        if (count($categoryIds) === 1) {
            $questCategoryId = $categoryIds[0];
            $parentCategoryId = (int) ($freelancer->questCategoryPreferences->first()?->parent_id ?? 0) ?: null;
        } elseif (count($categoryIds) > 1) {
            $profileCategoryIds = $categoryIds;
        }

        $budgetMaxMinor = $verification->freelancerProposalLimitMinor($freelancer);
        $budgetMaxNgn = $budgetMaxMinor > 0 ? $budgetMaxMinor / 100 : null;

        return array_merge($queryFilters, [
            'q' => '',
            'state_id' => $freelancer->state_id ? (int) $freelancer->state_id : null,
            'local_government_id' => null,
            'parent_category_id' => $parentCategoryId,
            'quest_category_id' => $questCategoryId,
            'category_ids' => $profileCategoryIds,
            'budget_min' => null,
            'budget_max' => $budgetMaxMinor > 0 ? $budgetMaxMinor : null,
            'budget_min_ngn' => null,
            'budget_max_ngn' => $budgetMaxNgn,
            'skill' => '',
            'sort' => 'posted_desc',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['state_id'])) {
            $query->where('state_id', (int) $filters['state_id']);
        }

        if (! empty($filters['local_government_id'])) {
            $query->where('local_government_id', (int) $filters['local_government_id']);
        }

        if (! empty($filters['category_ids'])) {
            $ids = collect($filters['category_ids'])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();

            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('quest_category_id', $ids);
            }
        } elseif (! empty($filters['quest_category_id'])) {
            $query->where('quest_category_id', (int) $filters['quest_category_id']);
        } elseif (! empty($filters['parent_category_id'])) {
            $leafIds = QuestCategory::query()
                ->where('parent_id', (int) $filters['parent_category_id'])
                ->pluck('id');

            if ($leafIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('quest_category_id', $leafIds);
            }
        }

        if (! empty($filters['budget_min'])) {
            $query->where('budget_amount_minor', '>=', (int) $filters['budget_min']);
        }

        if (! empty($filters['budget_max'])) {
            $query->where(function (Builder $inner) use ($filters): void {
                $inner->whereNull('budget_amount_minor')
                    ->orWhere('budget_amount_minor', '<=', (int) $filters['budget_max']);
            });
        }

        if (! empty($filters['skill'])) {
            $needle = mb_strtolower((string) $filters['skill']);
            $query->where(function (Builder $inner) use ($needle): void {
                $inner->whereRaw('LOWER(CAST(required_skills AS CHAR)) LIKE ?', ['%'.$needle.'%']);
            });
        }

        if (! empty($filters['q'])) {
            $term = '%'.mb_strtolower((string) $filters['q']).'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$term])
                    ->orWhereHas('questCategory', fn (Builder $cat) => $cat->whereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('questCategory.parent', fn (Builder $cat) => $cat->whereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('stateModel', fn (Builder $state) => $state->whereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('localGovernment', fn (Builder $lga) => $lga->whereRaw('LOWER(name) LIKE ?', [$term]));
            });
        }
    }

    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'posted_asc' => $query->oldest('created_at'),
            'budget_desc' => $query->orderByDesc('budget_amount_minor')->latest('created_at'),
            'budget_asc' => $query->orderBy('budget_amount_minor')->latest('created_at'),
            'deadline_asc' => $query->orderByRaw('delivery_deadline IS NULL')->orderBy('delivery_deadline')->latest('created_at'),
            'match_desc' => $query->latest('created_at'),
            default => $query->latest('created_at'),
        };
    }

    protected function baseQuery(): Builder
    {
        return Quest::query()
            ->where('status', QuestStatus::Open)
            ->where(fn (Builder $query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminQuestStatus::Suspended->value))
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id');
    }
}
