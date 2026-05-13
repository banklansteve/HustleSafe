<?php

namespace App\Http\Controllers;

use App\Enums\PortfolioStatus;
use App\Enums\QuestStatus;
use App\Enums\ReviewStatus;
use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioRequest;
use App\Models\Portfolio;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\Review;
use App\Models\User;
use App\Notifications\PortfolioFavoritedNotification;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FreelancerPortfolioController extends Controller
{
    public function __construct(
        protected PortfolioService $portfolioService,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'latest');

        $query = Portfolio::query()
            ->with([
                'user:id,first_name,name,slug,avatar_url',
                'category:id,name,slug',
                'subcategory:id,name,slug',
            ])
            ->where('status', PortfolioStatus::Published)
            ->where('admin_hidden', false);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }

        match ($sort) {
            'popular' => $query->orderByDesc('favorites_count')->orderByDesc('published_at'),
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        $portfolios = $query->paginate(12)->withQueryString();

        return Inertia::render('Portfolio/Index', [
            'portfolios' => $portfolios->through(fn (Portfolio $p) => $this->mapPublicCard($p)),
            'filters' => [
                'q' => $q,
                'sort' => $sort,
            ],
        ]);
    }

    public function manage(Request $request): Response
    {
        $user = $request->user();
        if ($user->role?->slug !== 'freelancer') {
            abort(403);
        }

        $q = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'latest');
        $statusFilter = (string) $request->query('status', 'all');

        $query = Portfolio::query()
            ->with(['category:id,name', 'subcategory:id,name'])
            ->where('user_id', $user->id);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }

        if ($statusFilter === 'draft') {
            $query->where('status', PortfolioStatus::Draft);
        } elseif ($statusFilter === 'published') {
            $query->where('status', PortfolioStatus::Published);
        }

        match ($sort) {
            'popular' => $query->orderByDesc('favorites_count')->orderByDesc('updated_at'),
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $portfolios = $query->paginate(10)->withQueryString();

        return Inertia::render('Portfolio/Manage', [
            'portfolios' => $portfolios->through(fn (Portfolio $p) => $this->mapManageCard($p)),
            'filters' => [
                'q' => $q,
                'sort' => $sort,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $this->authorize('create', Portfolio::class);

        return Inertia::render('Portfolio/Form', [
            'mode' => 'create',
            'portfolio' => null,
            'categoryTree' => $this->categoryTree(),
            'completedQuests' => $this->completedQuestOptions($user),
        ]);
    }

    public function store(StorePortfolioRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('create', Portfolio::class);

        $data = $request->validated();
        $status = $request->enum('status', PortfolioStatus::class);

        $portfolio = DB::transaction(function () use ($user, $data, $status, $request) {
            $slug = $this->portfolioService->uniqueSlugFromTitle($data['title']);

            $portfolio = Portfolio::query()->create([
                'user_id' => $user->id,
                'quest_id' => $data['quest_id'] ?? null,
                'category_id' => $data['category_id'],
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'slug' => $slug,
                'started_at' => $data['started_at'] ?? null,
                'completed_at' => $data['completed_at'] ?? null,
                'project_cost_minor' => $data['project_cost_minor'] ?? null,
                'status' => $status,
                'admin_hidden' => false,
                'published_at' => $status === PortfolioStatus::Published ? now() : null,
            ]);

            $uploads = $request->file('files', []);
            if ($uploads !== []) {
                $this->portfolioService->storeUploads($portfolio, $uploads);
            }

            return $portfolio->fresh(['files']);
        });

        $msg = $status === PortfolioStatus::Published
            ? __('Portfolio published — it is live in the gallery.')
            : __('Draft saved — only you can see it until you publish.');

        return redirect()->route('portfolio.show', $portfolio)->with('success', $msg);
    }

    public function show(Request $request, Portfolio $portfolio): Response
    {
        $this->authorize('view', $portfolio);

        $user = $request->user();
        $portfolio->load([
            'files',
            'user:id,first_name,name,slug,avatar_url',
            'category:id,name,slug',
            'subcategory:id,name,slug',
            'quest:id,title,uuid,completed_at,budget_amount_minor',
        ]);

        $review = null;
        if ($portfolio->quest_id !== null) {
            $review = Review::query()
                ->where('quest_id', $portfolio->quest_id)
                ->where('reviewee_id', $portfolio->user_id)
                ->where('reviewer_party', 'client')
                ->where('status', ReviewStatus::Published)
                ->with('reviewer:id,first_name,name')
                ->first();
        }

        $isOwner = $user !== null && $user->id === $portfolio->user_id;
        $favorited = false;
        if ($user !== null && $portfolio->isVisibleToPublic()) {
            $favorited = $portfolio->favoritedBy()->where('user_id', $user->id)->exists();
        }

        return Inertia::render('Portfolio/Show', [
            'portfolio' => $this->mapPortfolioDetail($portfolio, $review),
            'isOwner' => $isOwner,
            'favorited' => $favorited,
        ]);
    }

    public function edit(Request $request, Portfolio $portfolio): Response
    {
        $this->authorize('update', $portfolio);

        $user = $request->user();
        $portfolio->load(['files', 'category:id,name', 'subcategory:id,name']);

        return Inertia::render('Portfolio/Form', [
            'mode' => 'edit',
            'portfolio' => $this->mapPortfolioForForm($portfolio),
            'categoryTree' => $this->categoryTree(),
            'completedQuests' => $this->completedQuestOptions($user),
        ]);
    }

    public function update(UpdatePortfolioRequest $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $data = $request->validated();
        $status = $request->enum('status', PortfolioStatus::class);

        DB::transaction(function () use ($portfolio, $data, $status, $request) {
            if (! empty($data['remove_file_ids'])) {
                $this->portfolioService->deleteFiles($portfolio, $data['remove_file_ids']);
            }

            $titleChanged = $portfolio->title !== $data['title'];
            $slug = $titleChanged
                ? $this->portfolioService->uniqueSlugFromTitle($data['title'], $portfolio->id)
                : $portfolio->slug;

            $portfolio->fill([
                'quest_id' => $data['quest_id'] ?? null,
                'category_id' => $data['category_id'],
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'slug' => $slug,
                'started_at' => $data['started_at'] ?? null,
                'completed_at' => $data['completed_at'] ?? null,
                'project_cost_minor' => $data['project_cost_minor'] ?? null,
                'status' => $status,
            ]);

            if ($status === PortfolioStatus::Published && $portfolio->published_at === null) {
                $portfolio->published_at = now();
            }

            $portfolio->save();

            $uploads = $request->file('files', []);
            if ($uploads !== []) {
                $this->portfolioService->storeUploads($portfolio, $uploads);
            }
        });

        $portfolio->refresh();

        return redirect()->route('portfolio.show', $portfolio)->with('success', __('Portfolio updated.'));
    }

    public function destroy(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return redirect()->route('portfolio.manage')->with('success', __('Portfolio removed.'));
    }

    public function favorite(Request $request, Portfolio $portfolio): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $this->authorize('favorite', $portfolio);

        $notify = false;

        DB::transaction(function () use ($portfolio, $user, &$notify) {
            $exists = $portfolio->favoritedBy()->where('user_id', $user->id)->exists();
            if ($exists) {
                $portfolio->favoritedBy()->detach($user->id);
                if ($portfolio->favorites_count > 0) {
                    $portfolio->decrement('favorites_count');
                }

                return;
            }
            $portfolio->favoritedBy()->attach($user->id);
            $portfolio->increment('favorites_count');
            $notify = true;
        });

        $portfolio->refresh();

        if ($notify) {
            $owner = $portfolio->user;
            if ($owner !== null && $owner->id !== $user->id) {
                $owner->notify(new PortfolioFavoritedNotification($portfolio, $user));
            }
        }

        $favorited = $portfolio->favoritedBy()->where('user_id', $user->id)->exists();

        return response()->json([
            'favorited' => $favorited,
            'favorites_count' => (int) $portfolio->favorites_count,
        ]);
    }

    /**
     * @return list<array{id:int,name:string,children:list<array{id:int,name:string}>}>
     */
    protected function categoryTree(): array
    {
        return QuestCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (QuestCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'children' => $c->children->map(fn (QuestCategory $ch) => [
                    'id' => $ch->id,
                    'name' => $ch->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,title:string,completed_at:?string}>
     */
    protected function completedQuestOptions(User $user): array
    {
        return Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', [
                QuestStatus::Completed,
                QuestStatus::Archived,
                QuestStatus::Closed,
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'title', 'completed_at'])
            ->map(fn (Quest $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'completed_at' => $q->completed_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapPublicCard(Portfolio $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'description_excerpt' => str($p->description)->limit(160)->toString(),
            'cover_url' => $p->coverUrl(),
            'favorites_count' => (int) $p->favorites_count,
            'published_at' => $p->published_at?->timezone('Africa/Lagos')->toIso8601String(),
            'owner' => [
                'name' => $p->user?->first_name ?: $p->user?->name,
                'slug' => $p->user?->slug,
                'avatar_url' => $p->user?->avatar_url,
            ],
            'category' => $p->category ? ['name' => $p->category->name] : null,
            'subcategory' => $p->subcategory ? ['name' => $p->subcategory->name] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapManageCard(Portfolio $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'status' => $p->status->value,
            'cover_url' => $p->coverUrl(),
            'favorites_count' => (int) $p->favorites_count,
            'updated_at' => $p->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'category' => $p->category?->name,
            'subcategory' => $p->subcategory?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapPortfolioForForm(Portfolio $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'description' => $p->description,
            'category_id' => $p->category_id,
            'subcategory_id' => $p->subcategory_id,
            'quest_id' => $p->quest_id,
            'started_at' => $p->started_at?->timezone('Africa/Lagos')->format('Y-m-d'),
            'completed_at' => $p->completed_at?->timezone('Africa/Lagos')->format('Y-m-d'),
            'project_cost_minor' => $p->project_cost_minor,
            'status' => $p->status->value,
            'files' => $p->files->map(fn ($f) => [
                'id' => $f->id,
                'url' => $f->url(),
                'mime_type' => $f->mime_type,
                'original_name' => $f->original_name,
                'is_image' => $f->isImage(),
                'is_video' => $f->isVideo(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapPortfolioDetail(Portfolio $p, ?Review $review): array
    {
        $quest = $p->quest;

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'description' => $p->description,
            'status' => $p->status->value,
            'cover_url' => $p->coverUrl(),
            'favorites_count' => (int) $p->favorites_count,
            'published_at' => $p->published_at?->timezone('Africa/Lagos')->toIso8601String(),
            'started_at' => $p->started_at?->timezone('Africa/Lagos')->toIso8601String(),
            'completed_at' => $p->completed_at?->timezone('Africa/Lagos')->toIso8601String(),
            'project_cost_display' => $this->formatNgnFromMinor((int) ($p->project_cost_minor ?? 0)),
            'category' => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
            'subcategory' => $p->subcategory ? ['id' => $p->subcategory->id, 'name' => $p->subcategory->name] : null,
            'owner' => [
                'name' => $p->user?->first_name ?: $p->user?->name,
                'slug' => $p->user?->slug,
                'avatar_url' => $p->user?->avatar_url,
            ],
            'quest' => $quest ? [
                'title' => $quest->title,
                'uuid' => $quest->uuid,
                'completed_at' => $quest->completed_at?->timezone('Africa/Lagos')->toIso8601String(),
                'budget_display' => $this->formatNgnFromMinor((int) ($quest->budget_amount_minor ?? 0)),
            ] : null,
            'review' => $review ? [
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'reviewer_label' => $review->reviewer?->first_name ?: $review->reviewer?->name,
            ] : null,
            'files' => $p->files->map(fn ($f) => [
                'id' => $f->id,
                'url' => $f->url(),
                'mime_type' => $f->mime_type,
                'original_name' => $f->original_name,
                'is_image' => $f->isImage(),
                'is_video' => $f->isVideo(),
            ])->values()->all(),
        ];
    }

    protected function formatNgnFromMinor(int $minorUnits): string
    {
        if ($minorUnits <= 0) {
            return '—';
        }
        $naira = $minorUnits / 100;

        return '₦'.number_format($naira, 0);
    }
}
