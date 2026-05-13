<?php

namespace App\Http\Controllers;

use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestPromotionTier;
use App\Enums\QuestStartTiming;
use App\Enums\QuestStatus;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Http\Requests\Quests\StoreQuestRequest;
use App\Http\Requests\Quests\UpdateQuestRequest;
use App\Models\Quest;
use App\Models\QuestBookmark;
use App\Models\QuestCategory;
use App\Models\QuestFile;
use App\Models\State;
use App\Models\User;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\QuestPublishedNotificationService;
use App\Services\QuestSlugService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuestController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user?->can('create', Quest::class) && ! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return redirect()->route('dashboard');
        }

        $quests = Quest::query()
            ->where('client_id', $user->id)
            ->with(['questCategory:id,name', 'stateModel:id,name'])
            ->latest('updated_at')
            ->paginate(12)
            ->through(fn (Quest $q) => $this->questListRow($q));

        return Inertia::render('Quests/Index', [
            'quests' => $quests,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user?->can('create', Quest::class)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Quests/Create', [
            'locations' => $this->locationsPayload(),
            'categoryTree' => $this->categoryTreePayload(),
            'startTimingOptions' => $this->startTimingOptions(),
            'maxBudgetMinor' => 100_000_000,
            'fieldProfileUrl' => route('quests.field-profile'),
        ]);
    }

    public function store(StoreQuestRequest $request, QuestPublishedNotificationService $notifier, QuestSlugService $slugService): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $publish = $request->boolean('publish_now', true);
        $status = $publish ? QuestStatus::Open : QuestStatus::Draft;
        $tagged = array_values(array_unique(array_map('intval', $data['tagged_freelancer_ids'] ?? [])));

        $slugInput = trim((string) ($data['slug'] ?? ''));
        $slug = $slugInput !== '' ? $slugInput : $slugService->uniqueSlugFromTitle($data['title']);

        $trafficUtm = $data['traffic_utm'] ?? null;
        if (is_array($trafficUtm)) {
            $trafficUtm = array_filter($trafficUtm, fn ($v) => $v !== null && $v !== '');
            $trafficUtm = $trafficUtm === [] ? null : $trafficUtm;
        } else {
            $trafficUtm = null;
        }

        $quest = DB::transaction(function () use ($request, $user, $data, $status, $tagged, $slug, $publish, $trafficUtm): Quest {
            $dueAt = now()->addDays((int) $data['estimated_completion_days']);

            $listingExpiresAt = null;
            if ($publish && ! empty($data['auto_listing_expiry_days'])) {
                $listingExpiresAt = now()->addDays((int) $data['auto_listing_expiry_days']);
            }

            $quest = Quest::query()->create([
                'client_id' => $user->id,
                'slug' => $slug,
                'title' => $data['title'],
                'description' => $data['description'],
                'quest_category_id' => $data['quest_category_id'],
                'state_id' => $data['state_id'],
                'local_government_id' => $data['local_government_id'],
                'city' => $data['city'],
                'status' => $status,
                'visibility' => QuestVisibility::from($data['visibility']),
                'freelancer_location_pref' => QuestFreelancerLocationPref::from($data['freelancer_location_pref']),
                'availability_need' => isset($data['availability_need']) && $data['availability_need']
                    ? QuestAvailabilityNeed::from($data['availability_need'])
                    : null,
                'project_type' => isset($data['project_type']) && $data['project_type']
                    ? QuestProjectType::from($data['project_type'])
                    : null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'team_size' => isset($data['team_size']) && $data['team_size']
                    ? QuestTeamSize::from($data['team_size'])
                    : null,
                'promotion_tier' => QuestPromotionTier::from($data['promotion_tier']),
                'auto_listing_expiry_days' => $data['auto_listing_expiry_days'] ?? null,
                'listing_expires_at' => $listingExpiresAt,
                'max_offers' => $data['max_offers'] ?? null,
                'traffic_source' => $data['traffic_source'] ?? null,
                'traffic_utm' => $trafficUtm,
                'budget_amount_minor' => $data['budget_amount_minor'],
                'start_timing' => QuestStartTiming::from($data['start_timing']),
                'estimated_completion_days' => $data['estimated_completion_days'],
                'estimated_delivery_date' => $data['estimated_delivery_date'] ?? null,
                'site_visits_allowed' => $request->boolean('site_visits_allowed'),
                'scheduled_start_date' => $data['scheduled_start_date'] ?? null,
                'due_at' => $dueAt,
            ]);

            $sort = 0;
            foreach ($request->file('files', []) as $uploaded) {
                if ($uploaded === null) {
                    continue;
                }
                $path = $uploaded->store("quests/{$quest->id}", 'public');
                QuestFile::query()->create([
                    'quest_id' => $quest->id,
                    'disk' => 'public',
                    'path' => $path,
                    'original_name' => $uploaded->getClientOriginalName(),
                    'mime_type' => $uploaded->getClientMimeType(),
                    'size_bytes' => $uploaded->getSize() ?: 0,
                    'sort_order' => $sort++,
                ]);
            }

            if ($tagged !== []) {
                $quest->invitedFreelancers()->syncWithoutDetaching($tagged);
            }

            return $quest->fresh(['files']);
        });

        if ($status === QuestStatus::Open) {
            $notifier->notifyAudiences($quest, $tagged);
        }

        return redirect()
            ->route('quests.show', $quest)
            ->with('success', $publish
                ? __('Your quest is live — freelancers have been alerted.')
                : __('Draft saved. Publish when you are ready.'));
    }

    public function show(Request $request, Quest $quest, FreelancerWorkspaceReadinessService $workspace): Response|SymfonyResponse
    {
        $this->authorize('view', $quest);

        $quest->load([
            'client:id,first_name,name,slug,avatar_url,username',
            'questCategory:id,name,parent_id',
            'questCategory.parent:id,name',
            'stateModel:id,name',
            'localGovernment:id,name',
            'files',
            'invitedFreelancers:id,first_name,name,slug,avatar_url',
        ]);

        $user = $request->user();
        if ($user && $user->id !== $quest->client_id) {
            $cacheKey = 'quest-view:'.$quest->id.':'.$user->id;
            if (Cache::add($cacheKey, 1, now()->addYear())) {
                $quest->increment('views_count');
            }
        }

        $summary = $user && $user->role?->slug === 'freelancer' ? $workspace->summarize($user) : null;
        $inviteOffer = $user
            && $user->role?->slug === 'freelancer'
            && $quest->visibility === QuestVisibility::InviteOnly
            && $quest->isInvitedFreelancer($user);

        $canOffer = $user
            && $user->role?->slug === 'freelancer'
            && ($summary['can_submit_offers'] ?? false)
            && ($workspace->matchesQuestCategory($user, $quest) || $inviteOffer);

        $myOffer = null;
        if ($user && $user->role?->slug === 'freelancer') {
            $myOffer = $quest->offers()->where('freelancer_id', $user->id)->first();
        }

        $isBookmarked = false;
        if ($user && $user->role?->slug === 'freelancer') {
            $isBookmarked = QuestBookmark::query()
                ->where('quest_id', $quest->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        $similar = Quest::query()
            ->where('id', '<>', $quest->id)
            ->where('status', QuestStatus::Open)
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->where('state_id', $quest->state_id)
            ->where('quest_category_id', $quest->quest_category_id)
            ->with(['questCategory:id,name', 'stateModel:id,name'])
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Quest $q) => $this->questCardPayload($q));

        $topFreelancers = User::query()
            ->whereRelation('role', 'slug', 'freelancer')
            ->where('users.id', '<>', $quest->client_id)
            ->where('state_id', $quest->state_id)
            ->whereHas('questCategoryPreferences', function ($q) use ($quest): void {
                $q->where('quest_categories.id', $quest->quest_category_id);
            })
            ->with(['trustMetrics'])
            ->limit(24)
            ->get()
            ->sortByDesc(fn (User $u) => (int) ($u->trustMetrics?->freelancer_trust_score ?? 0))
            ->take(6)
            ->values()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'first_name' => $u->first_name,
                'slug' => $u->slug,
                'avatar_url' => $u->avatar_url,
                'trust' => (int) ($u->trustMetrics?->freelancer_trust_score ?? 0),
            ])
            ->all();

        return Inertia::render('Quests/Show', [
            'quest' => $this->questDetailPayload($quest),
            'can_edit' => $user?->can('update', $quest) ?? false,
            'can_offer' => $canOffer,
            'workspace' => $summary ? array_merge(['enabled' => true], $summary) : ['enabled' => false],
            'my_offer' => $myOffer ? [
                'id' => $myOffer->id,
                'status' => $myOffer->status,
                'pitch' => $myOffer->pitch,
                'quoted_amount_minor' => $myOffer->quoted_amount_minor,
            ] : null,
            'is_bookmarked' => $isBookmarked,
            'similar_quests' => $similar,
            'top_freelancers' => $topFreelancers,
            'start_timing_options' => $this->startTimingOptions(),
            'form_options' => ($user?->can('update', $quest) ?? false)
                ? [
                    'locations' => $this->locationsPayload(),
                    'category_tree' => $this->categoryTreePayload(),
                ]
                : null,
        ]);
    }

    public function update(UpdateQuestRequest $request, Quest $quest): RedirectResponse
    {
        $data = $request->validated();
        if ($data === []) {
            return back();
        }

        $days = $data['estimated_completion_days'] ?? null;
        unset($data['estimated_completion_days']);

        $startTiming = $data['start_timing'] ?? null;
        unset($data['start_timing']);

        $quest->fill($data);

        if ($startTiming !== null) {
            $quest->start_timing = QuestStartTiming::from($startTiming);
        }

        if ($days !== null) {
            $quest->estimated_completion_days = (int) $days;
            $quest->due_at = now()->addDays((int) $days);
        }

        $quest->save();

        return back()->with('success', __('Quest updated.'));
    }

    public function destroy(Request $request, Quest $quest): RedirectResponse
    {
        $this->authorize('delete', $quest);

        foreach ($quest->files as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }
        $quest->delete();

        return redirect()->route('quests.index')->with('success', __('Quest removed.'));
    }

    public function searchFreelancers(Request $request, Quest $quest): SymfonyResponse
    {
        $this->authorize('manageInvites', $quest);

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::query()
            ->whereRelation('role', 'slug', 'freelancer')
            ->where('users.id', '<>', $request->user()->id)
            ->where(function ($w) use ($q): void {
                $w->where('first_name', 'like', '%'.$q.'%')
                    ->orWhere('last_name', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%')
                    ->orWhere('username', 'like', '%'.$q.'%');
            })
            ->orderBy('first_name')
            ->limit(12)
            ->get(['id', 'first_name', 'name', 'slug', 'avatar_url']);

        return response()->json([
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'label' => $u->first_name ?: $u->name,
                'name' => $u->name,
                'slug' => $u->slug,
                'avatar_url' => $u->avatar_url,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function questListRow(Quest $q): array
    {
        return [
            'uuid' => $q->uuid,
            'reference_code' => $q->reference_code,
            'title' => $q->title,
            'status' => $q->status->value,
            'category' => $q->questCategory?->name,
            'state' => $q->stateModel?->name,
            'city' => $q->city,
            'budget_minor' => (int) ($q->budget_amount_minor ?? 0),
            'updated_at' => $q->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function questCardPayload(Quest $q): array
    {
        return [
            'uuid' => $q->uuid,
            'title' => $q->title,
            'category' => $q->questCategory?->name,
            'state' => $q->stateModel?->name,
            'city' => $q->city,
            'budget_minor' => (int) ($q->budget_amount_minor ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function questDetailPayload(Quest $quest): array
    {
        return [
            'slug' => $quest->slug,
            'visibility' => $quest->visibility?->value,
            'freelancer_location_pref' => $quest->freelancer_location_pref?->value,
            'availability_need' => $quest->availability_need?->value,
            'project_type' => $quest->project_type?->value,
            'estimated_hours' => $quest->estimated_hours,
            'team_size' => $quest->team_size?->value,
            'promotion_tier' => $quest->promotion_tier?->value,
            'auto_listing_expiry_days' => $quest->auto_listing_expiry_days,
            'listing_expires_at' => $quest->listing_expires_at?->timezone('Africa/Lagos')->toIso8601String(),
            'max_offers' => $quest->max_offers,
            'views_count' => (int) $quest->views_count,
            'offers_count' => (int) $quest->offers_count,
            'saves_count' => (int) $quest->saves_count,
            'traffic_source' => $quest->traffic_source,
            'traffic_utm' => $quest->traffic_utm,
            'estimated_delivery_date' => $quest->estimated_delivery_date?->toDateString(),
            'uuid' => $quest->uuid,
            'reference_code' => $quest->reference_code,
            'title' => $quest->title,
            'description' => $quest->description,
            'status' => $quest->status->value,
            'budget_minor' => (int) ($quest->budget_amount_minor ?? 0),
            'start_timing' => $quest->start_timing?->value,
            'estimated_completion_days' => $quest->estimated_completion_days,
            'site_visits_allowed' => (bool) $quest->site_visits_allowed,
            'scheduled_start_date' => $quest->scheduled_start_date?->toDateString(),
            'due_at' => $quest->due_at?->timezone('Africa/Lagos')->toIso8601String(),
            'category' => $quest->questCategory ? [
                'id' => $quest->questCategory->id,
                'name' => $quest->questCategory->name,
                'parent_name' => $quest->questCategory->parent?->name,
            ] : null,
            'quest_category_id' => $quest->quest_category_id,
            'state_id' => $quest->state_id,
            'local_government_id' => $quest->local_government_id,
            'city' => $quest->city,
            'location' => [
                'state' => $quest->stateModel?->name,
                'lga' => $quest->localGovernment?->name,
                'city' => $quest->city,
            ],
            'client' => [
                'name' => $quest->client?->name,
                'first_name' => $quest->client?->first_name,
                'username' => $quest->client?->username,
                'slug' => $quest->client?->slug,
                'avatar_url' => $quest->client?->avatar_url,
            ],
            'files' => $quest->files->map(fn (QuestFile $f) => [
                'id' => $f->id,
                'url' => $f->url(),
                'name' => $f->original_name,
                'mime' => $f->mime_type,
                'is_image' => $f->isImage(),
            ])->values()->all(),
            'invited' => $quest->invitedFreelancers->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'slug' => $u->slug,
                'avatar_url' => $u->avatar_url,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function startTimingOptions(): array
    {
        return collect(QuestStartTiming::cases())->map(fn (QuestStartTiming $t) => [
            'value' => $t->value,
            'label' => match ($t) {
                QuestStartTiming::Urgent48h => __('Urgent — start within 48 hours'),
                QuestStartTiming::ThisWeek => __('This week'),
                QuestStartTiming::NextTwoWeeks => __('Within the next two weeks'),
                QuestStartTiming::Flexible => __('Flexible — we will align in chat'),
                QuestStartTiming::Scheduled => __('Scheduled start (pick a date)'),
                QuestStartTiming::WindowShopping => __('Browsing — planning for later (no firm start yet)'),
            },
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function categoryTreePayload(): array
    {
        return QuestCategory::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (QuestCategory $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'children' => $p->children->map(fn (QuestCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, State>
     */
    protected function locationsPayload()
    {
        return State::query()
            ->with(['localGovernments:id,state_id,name'])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
