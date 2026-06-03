<?php

namespace App\Http\Controllers;

use App\Enums\AdminProposalStatus;
use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestStartTiming;
use App\Enums\QuestStatus;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Enums\AdminQuestStatus;
use App\Http\Requests\Quests\ExtendQuestListingRequest;
use App\Http\Requests\Quests\StoreQuestRequest;
use App\Http\Requests\Quests\UpdateQuestRequest;
use App\Jobs\ScanContentForModerationJob;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\QuestBookmark;
use App\Models\QuestCategory;
use App\Models\QuestConversationThread;
use App\Models\QuestFile;
use App\Models\QuestOffer;
use App\Models\State;
use App\Models\User;
use App\Notifications\QuestBriefUpdatedNotification;
use App\Notifications\QuestListingPulseNotification;
use App\Notifications\QuestPublishedClientConfirmationNotification;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\Quest\QuestListingExpiryService;
use App\Services\QuestCoverService;
use App\Services\QuestFileStorageService;
use App\Services\QuestFormFieldProfileService;
use App\Services\QuestPublishedNotificationService;
use App\Services\QuestSlugService;
use App\Services\Verification\VerificationEngineService;
use App\Support\PlatformSettings;
use App\Support\QuestCommerceUi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuestController extends Controller
{
    public function index(Request $request): Response|RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user?->can('create', Quest::class) && ! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return redirect()->route('dashboard');
        }

        $base = Quest::query()
            ->where('client_id', $user->id)
            ->with(['questCategory.parent:id,name', 'stateModel:id,name'])
            ->withCount('offers')
            ->latest('updated_at');

        if ($request->wantsJson()) {
            $page = max(1, (int) $request->query('page', 1));
            $paginator = (clone $base)->paginate(12, ['*'], 'page', $page);

            return response()->json([
                'data' => $paginator->getCollection()->map(fn (Quest $q) => $this->questListRow($q))->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ]);
        }

        $quests = (clone $base)->paginate(12)->through(fn (Quest $q) => $this->questListRow($q));

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

        $verificationEngine = app(VerificationEngineService::class);
        $clientLimit = $verificationEngine->clientPostingLimitMinor($user);

        return Inertia::render('Quests/Create', [
            'locations' => $this->locationsPayload(),
            'categoryTree' => $this->categoryTreePayload(),
            'startTimingOptions' => $this->startTimingOptions(),
            'maxBudgetMinor' => max(10_000, min(500_000_000, $clientLimit > 0 ? $clientLimit : 500_000_000)),
            'minBudgetMinor' => 10_000,
            'verificationLimit' => $clientLimit,
            'fieldProfileUrl' => route('quests.field-profile'),
            'freelancersYouFollow' => $this->questCreateFreelancerNetworkFollowing($user),
            'freelancersFollowingYou' => $this->questCreateFreelancerNetworkFollowers($user),
            'quest_stats_hints' => $this->questCreateStatsHints(),
            'proposal_deadline_bounds' => PlatformSettings::proposalDeadlineBounds(),
        ]);
    }

    public function store(
        StoreQuestRequest $request,
        QuestPublishedNotificationService $notifier,
        QuestSlugService $slugService,
        QuestFileStorageService $questFiles,
        QuestCoverService $cover,
        VerificationEngineService $verificationEngine,
        QuestListingExpiryService $listingExpiry,
    ): RedirectResponse {
        $user = $request->user();
        $data = $request->validated();
        app(\App\Services\Onboarding\OnboardingPostingGateService::class)->assertCanPost($user, 'budget_amount_minor');
        $verificationEngine->assertClientCanPostQuest($user, (int) ($data['budget_amount_minor'] ?? 0));
        $publish = $request->boolean('publish_now', true);
        $category = QuestCategory::query()->with('parent')->find((int) $data['quest_category_id']);
        $approvalRule = $category?->high_value_approval_enabled ? $category : ($category?->parent?->high_value_approval_enabled ? $category->parent : null);
        $requiresApproval = $publish
            && $approvalRule
            && $approvalRule->high_value_threshold_minor
            && (int) $data['budget_amount_minor'] >= (int) $approvalRule->high_value_threshold_minor;

        $qualityGate = app(\App\Services\Quest\QuestQualityGateService::class)->evaluate($data);
        if ($publish && ! $qualityGate['passed']) {
            $publish = false;
        }

        $status = $publish ? ($requiresApproval ? QuestStatus::PendingReview : QuestStatus::Open) : QuestStatus::Draft;
        $tagged = array_values(array_unique(array_map('intval', $data['tagged_freelancer_ids'] ?? [])));

        $slug = $slugService->uniqueSlugFromTitle($data['title']);

        $trafficUtm = $data['traffic_utm'] ?? null;
        if (is_array($trafficUtm)) {
            $trafficUtm = array_filter($trafficUtm, fn ($v) => $v !== null && $v !== '');
            $trafficUtm = $trafficUtm === [] ? null : $trafficUtm;
        } else {
            $trafficUtm = null;
        }

        $uploadedFiles = array_values(array_filter($request->file('files', [])));

        $quest = DB::transaction(function () use ($request, $user, $data, $status, $tagged, $slug, $publish, $trafficUtm, $questFiles, $uploadedFiles, $qualityGate, $listingExpiry): Quest {
            $dueAt = now()->addDays((int) $data['estimated_completion_days']);

            $listingDays = $publish
                ? $listingExpiry->resolveDaysForCreate(isset($data['auto_listing_expiry_days']) ? (int) $data['auto_listing_expiry_days'] : null)
                : null;
            $listingExpiresAt = $publish && $listingDays ? now()->addDays($listingDays) : null;

            $clientEditUntil = null;
            if ($publish) {
                $hours = max(1, (int) config('quests.client_edit_window_hours', 48));
                $clientEditUntil = now()->addHours($hours);
            }

            $quest = Quest::query()->create([
                'client_id' => $user->id,
                'slug' => $slug,
                'title' => $data['title'],
                'description' => $data['description'],
                'quality_gate_feedback' => $publish ? null : ($qualityGate['passed'] ? null : $qualityGate['issues']),
                'quality_gate_failed_at' => $publish ? null : ($qualityGate['passed'] ? null : now()),
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
                'auto_listing_expiry_days' => $listingDays,
                'listing_expires_at' => $listingExpiresAt,
                'listing_extension_count' => 0,
                'client_edit_until' => $clientEditUntil,
                'max_offers' => $data['max_offers'] ?? null,
                'traffic_source' => $data['traffic_source'] ?? null,
                'traffic_utm' => $trafficUtm,
                'terms_accepted_at' => $request->boolean('accepted_terms') ? now() : null,
                'budget_amount_minor' => $data['budget_amount_minor'],
                'start_timing' => QuestStartTiming::from($data['start_timing']),
                'estimated_completion_days' => $data['estimated_completion_days'],
                'estimated_delivery_date' => $data['estimated_delivery_date'] ?? null,
                'site_visits_allowed' => $request->boolean('site_visits_allowed'),
                'site_access_level' => $data['site_access_level'] ?? null,
                'pets_on_site' => $request->has('pets_on_site') ? $request->boolean('pets_on_site') : null,
                'pets_detail' => $data['pets_detail'] ?? null,
                'scheduled_start_date' => $data['scheduled_start_date'] ?? null,
                'due_at' => $dueAt,
            ]);

            $sort = 0;
            foreach ($uploadedFiles as $uploaded) {
                if ($uploaded === null) {
                    continue;
                }
                $questFiles->store($quest, $uploaded, $sort++);
            }

            if ($tagged !== []) {
                $quest->invitedFreelancers()->syncWithoutDetaching($tagged);
            }

            return $quest->fresh(['files']);
        });

        $cover->sync($quest);
        $verificationEngine->flagDuplicateQuestIfNeeded($quest);
        $verificationEngine->runAnomalyChecks($user, $quest);

        if ($status === QuestStatus::Open) {
            $notifier->notifyAudiences($quest, $tagged);
            $user->notify(new QuestPublishedClientConfirmationNotification($quest));
            $quest->loadMissing(['client', 'questCategory', 'stateModel']);
            app(AdminActivityFeedService::class)->record(
                'jobs',
                'quest.posted',
                'New quest posted',
                "{$user->name} posted {$quest->title}",
                app(AdminActivityFeedService::class)->entities([
                    ['type' => 'user', 'id' => $user->id, 'label' => $user->name],
                    ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
                ]),
                [
                    'budget' => '₦'.number_format(((int) $quest->budget_amount_minor) / 100, 2),
                    'category' => $quest->questCategory?->name,
                    'state' => $quest->stateModel?->name,
                    'location' => $quest->city,
                ],
                (int) $quest->budget_amount_minor,
                $user,
                Quest::class,
                $quest->id,
                $quest->state_id,
                $quest->local_government_id,
                $quest->quest_category_id,
            );
        }

        ScanContentForModerationJob::dispatch(Quest::class, (int) $quest->id)->afterResponse();

        $redirect = redirect()
            ->route('quests.show', $quest)
            ->with('success', $requiresApproval
                ? __('Your quest was submitted for admin review because it is above this category’s high-value threshold.')
                : ($publish
                ? __('Your quest is live — freelancers have been alerted.')
                : ($qualityGate['passed']
                    ? __('Draft saved. Publish when you are ready.')
                    : __('Your quest was saved as a draft. Fix the quality issues below before publishing.'))));

        if (! $qualityGate['passed'] && ! $publish) {
            $redirect->with('quality_gate_issues', $qualityGate['issues']);
        }

        if ($publish && ! $requiresApproval) {
            $redirect->with('quest_submitted_next_steps', true);
        }

        return $redirect;
    }

    public function show(Request $request, Quest $quest, FreelancerWorkspaceReadinessService $workspace): Response|SymfonyResponse
    {
        $this->authorize('view', $quest);

        if (($quest->slug ?? '') !== '' && (string) $request->segment(2) === $quest->uuid) {
            return redirect()->route('quests.show', $quest, 301);
        }

        $quest->load([
            'client:id,first_name,name,slug,avatar_url,username,role_id',
            'client.role:id,slug',
            'questCategory:id,name,parent_id',
            'questCategory.parent:id,name',
            'stateModel:id,name',
            'localGovernment:id,name',
            'files',
            'visibleAdminQuestNotices.creator:id,name',
            'invitedFreelancers:id,first_name,name,slug,avatar_url',
            'acceptedOffer',
        ]);

        $user = $request->user();
        if ($user && (int) $user->id !== (int) $quest->client_id && $user->role?->slug === 'freelancer') {
            $cacheKey = 'quest-view:'.$quest->id.':'.$user->id;
            if (Cache::add($cacheKey, 1, now()->addYear())) {
                $quest->increment('views_count');
                $vc = (int) $quest->fresh()->views_count;
                if (in_array($vc, [1, 3, 5, 10, 20, 50], true)) {
                    $quest->client?->notify(new QuestListingPulseNotification($quest->fresh(), $vc));
                }
            }
        }

        $summary = $user && $user->role?->slug === 'freelancer' ? $workspace->summarize($user) : null;
        $inviteOffer = $user
            && $user->role?->slug === 'freelancer'
            && $quest->visibility === QuestVisibility::InviteOnly
            && $quest->isInvitedFreelancer($user);

        $verificationEngine = app(VerificationEngineService::class);
        $freelancerLimit = $user && $user->role?->slug === 'freelancer' ? $verificationEngine->freelancerProposalLimitMinor($user) : null;
        $categoryMatch = $user
            && $user->role?->slug === 'freelancer'
            && ($workspace->matchesQuestCategory($user, $quest) || $inviteOffer);
        $verificationCanOffer = $user
            && $user->role?->slug === 'freelancer'
            && $verificationEngine->freelancerCanProposeForBudget($user, (int) $quest->budget_amount_minor);

        $canOffer = $user
            && $user->role?->slug === 'freelancer'
            && ($summary['can_submit_proposals'] ?? false)
            && $categoryMatch
            && $verificationCanOffer;

        $myOffer = null;
        if ($user && $user->role?->slug === 'freelancer') {
            $myOffer = $quest->offers()
                ->where('freelancer_id', $user->id)
                ->whereIn('status', ['submitted', 'shortlisted', 'pending_award', 'accepted'])
                ->excludingAdminSuspended()
                ->first()
                ?? $quest->offers()
                    ->where('freelancer_id', $user->id)
                    ->excludingAdminSuspended()
                    ->latest('id')
                    ->first();
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
            ->where(fn ($query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminQuestStatus::Suspended->value))
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->where('state_id', $quest->state_id)
            ->where('quest_category_id', $quest->quest_category_id)
            ->with(['questCategory:id,name', 'stateModel:id,name'])
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Quest $q) => $this->questCardPayload($q));

        $matcher = app(\App\Services\Matching\QuestFreelancerMatcher::class);
        $matchResult = $matcher->recommendationsForQuest(
            $quest,
            (int) config('quest_matching.client_recommendations_limit', 10),
        );
        $topFreelancers = $matchResult['recommendations'];
        $freelancerMatchStats = $matchResult['stats'];

        $fromClientQuests = Quest::query()
            ->where('id', '<>', $quest->id)
            ->where('client_id', $quest->client_id)
            ->where('status', QuestStatus::Open)
            ->where(fn ($query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminQuestStatus::Suspended->value))
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->with(['questCategory:id,name', 'stateModel:id,name'])
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Quest $q) => $this->questCardPayload($q));

        $categoryQuestsOtherAreas = Quest::query()
            ->where('id', '<>', $quest->id)
            ->where('quest_category_id', $quest->quest_category_id)
            ->where('state_id', '<>', $quest->state_id)
            ->where('status', QuestStatus::Open)
            ->where(fn ($query) => $query->whereNull('admin_status')->orWhere('admin_status', '<>', AdminQuestStatus::Suspended->value))
            ->where('visibility', QuestVisibility::Public)
            ->whereNull('freelancer_id')
            ->with(['questCategory:id,name', 'stateModel:id,name'])
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Quest $q) => $this->questCardPayload($q));

        $canUseQuestMessaging = $user
            && $user->role?->slug === 'freelancer'
            && $workspace->freelancerMayUseQuestMessaging($user, $quest);

        $questMessageThreads = [];
        if ($user && (int) $user->id === (int) $quest->client_id) {
            $offerFreelancerIds = QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->visibleInClientInbox()
                ->pluck('freelancer_id');

            $threadFreelancerIds = QuestConversationThread::query()
                ->where('quest_id', $quest->id)
                ->where('messages_count', '>', 0)
                ->pluck('freelancer_id');

            $ids = $offerFreelancerIds->merge($threadFreelancerIds)->unique()->filter()->values();

            if ($ids->isNotEmpty()) {
                $questMessageThreads = User::query()
                    ->whereIn('id', $ids)
                    ->whereRelation('role', 'slug', 'freelancer')
                    ->whereNotNull('slug')
                    ->orderBy('first_name')
                    ->get(['id', 'name', 'first_name', 'slug', 'avatar_url'])
                    ->map(fn (User $fr) => [
                        'name' => $fr->name,
                        'first_name' => $fr->first_name,
                        'slug' => $fr->slug,
                        'avatar_url' => $fr->avatar_url,
                        'messages_url' => route('quests.messages.show', [$quest->getRouteKey(), $fr->slug]),
                    ])
                    ->values()
                    ->all();
            }
        }

        $isQuestOwner = $user && (int) $user->id === (int) $quest->client_id;

        $questPayload = $this->questDetailPayload($quest, $user);
        if ($user !== null && $quest->isParty($user)) {
            $questPayload = array_merge($questPayload, [
                'escrow_status' => $quest->escrow_status,
                'accepted_quest_offer_id' => $quest->accepted_quest_offer_id,
            ]);
            $commerce = QuestCommerceUi::disputeForQuest($quest, $user);
            if ($quest->acceptedOffer !== null) {
                $commerce = array_merge($commerce, QuestCommerceUi::fundingForOffer($quest, $quest->acceptedOffer, $user));
            }
            $questPayload['commerce'] = array_merge($commerce, QuestCommerceUi::partyExtras($quest, $user));
        }

        return Inertia::render('Quests/Show', [
            'quest' => $questPayload,
            'is_quest_owner' => (bool) ($user && $user->id === $quest->client_id),
            'can_edit' => $user?->can('update', $quest) ?? false,
            'can_offer' => $canOffer,
            'category_match' => $categoryMatch,
            'verification_access' => $user && $user->role?->slug === 'freelancer' ? [
                'earned_level' => $verificationEngine->storedLevel($user),
                'effective_level' => $verificationEngine->effectiveLevel($user),
                'proposal_limit_minor' => $freelancerLimit,
                'earned_proposal_limit_minor' => $verificationEngine->limitAtLevel($user, $verificationEngine->storedLevel($user)),
                'limit_capped' => $user->custom_freelancer_proposal_limit_minor !== null
                    && $freelancerLimit < $verificationEngine->limitAtLevel($user, $verificationEngine->storedLevel($user)),
                'can_submit_for_budget' => $verificationCanOffer,
                'missing_for_next_level' => $verificationEngine->missingForNextLevelPublic($user),
                'verifications_url' => route('verifications.index'),
            ] : null,
            'workspace' => $summary ? array_merge(['enabled' => true], $summary) : ['enabled' => false],
            'my_offer' => $myOffer ? [
                'id' => $myOffer->id,
                'status' => $myOffer->status,
                'pitch' => $myOffer->pitch,
                'quoted_amount_minor' => $myOffer->quoted_amount_minor,
                'show_url' => route('quests.proposals.show', [$quest, $myOffer]),
            ] : null,
            'is_bookmarked' => $isBookmarked,
            'similar_quests' => $similar,
            'from_client_quests' => $fromClientQuests,
            'category_quests_other_areas' => $categoryQuestsOtherAreas,
            'top_freelancers' => $topFreelancers,
            'freelancer_match_stats' => $freelancerMatchStats,
            'quest_field_profile' => app(QuestFormFieldProfileService::class)->profileForLeafCategoryId((int) $quest->quest_category_id),
            'field_profile_url' => route('quests.field-profile'),
            'can_use_quest_messaging' => $canUseQuestMessaging,
            'messages_url' => $canUseQuestMessaging ? route('quests.messages.show', [$quest->getRouteKey()]) : null,
            'quest_message_threads' => $questMessageThreads,
            'start_timing_options' => $this->startTimingOptions(),
            'form_options' => ($user?->can('update', $quest) ?? false)
                ? [
                    'locations' => $this->locationsPayload(),
                    'category_tree' => $this->categoryTreePayload(),
                ]
                : null,
            'client_proposals' => $isQuestOwner ? $this->clientProposalSummariesForQuest($quest) : [],
            'client_proposals_hub_url' => $isQuestOwner ? route('quests.client.proposals.index', $quest) : null,
        ]);
    }

    public function update(UpdateQuestRequest $request, Quest $quest, VerificationEngineService $verificationEngine): RedirectResponse
    {
        $data = $request->validated();
        if ($data === []) {
            return back();
        }

        if (array_key_exists('budget_amount_minor', $data) && (int) $quest->client_id === (int) $request->user()->id) {
            $verificationEngine->assertClientCanPostQuest($request->user(), (int) $data['budget_amount_minor']);
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

        ScanContentForModerationJob::dispatch(Quest::class, (int) $quest->id)->afterResponse();

        if ($quest->status === QuestStatus::Open
            && $quest->client_id === $request->user()->id) {
            $recipientIds = QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
                ->excludingAdminSuspended()
                ->pluck('freelancer_id')
                ->unique()
                ->values();

            if ($recipientIds->isNotEmpty()) {
                $recipients = User::query()->whereIn('id', $recipientIds)->get();
                foreach ($recipients as $recipient) {
                    $recipient->notify(new QuestBriefUpdatedNotification($quest));
                }
            }
        }

        return back()->with('success', __('Quest updated.'));
    }

    public function destroy(Request $request, Quest $quest): RedirectResponse
    {
        $this->authorize('delete', $quest);

        $quest->delete();

        return redirect()->route('quests.index')->with('success', __('Quest removed.'));
    }

    public function extendListing(ExtendQuestListingRequest $request, Quest $quest, QuestListingExpiryService $listingExpiry): RedirectResponse
    {
        $this->authorize('update', $quest);

        $listingExpiry->extend(
            $quest,
            $request->user(),
            (int) $request->validated('additional_days'),
            (string) $request->validated('reason'),
        );

        return back()->with('success', __('Proposal deadline extended. Freelancers who submitted proposals have been notified.'));
    }

    public function repost(Request $request, Quest $quest, QuestListingExpiryService $listingExpiry): RedirectResponse
    {
        $this->authorize('update', $quest);

        $fresh = $listingExpiry->repost($quest, $request->user());

        return redirect()
            ->route('quests.show', $fresh)
            ->with('success', __('Quest reposted with a fresh proposal window.'));
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
     * @return list<array{id: int, name: string, first_name: ?string, slug: ?string, avatar_url: ?string}>
     */
    protected function questCreateFreelancerNetworkFollowing(User $client): array
    {
        if (! Schema::hasTable('user_follows')) {
            return [];
        }

        return $client->followingUsers()
            ->whereRelation('role', 'slug', 'freelancer')
            ->orderBy('first_name')
            ->orderBy('name')
            ->limit(200)
            ->get(['users.id', 'first_name', 'name', 'slug', 'avatar_url'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'first_name' => $u->first_name,
                'slug' => $u->slug,
                'avatar_url' => $u->avatar_url,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, first_name: ?string, slug: ?string, avatar_url: ?string}>
     */
    protected function questCreateFreelancerNetworkFollowers(User $client): array
    {
        if (! Schema::hasTable('user_follows')) {
            return [];
        }

        return $client->followers()
            ->whereRelation('role', 'slug', 'freelancer')
            ->orderBy('first_name')
            ->orderBy('name')
            ->limit(200)
            ->get(['users.id', 'first_name', 'name', 'slug', 'avatar_url'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'first_name' => $u->first_name,
                'slug' => $u->slug,
                'avatar_url' => $u->avatar_url,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function questListRow(Quest $q): array
    {
        $cat = $q->questCategory;
        $parent = $cat?->parent;
        $user = auth()->user();

        return [
            'uuid' => $q->uuid,
            'slug' => $q->slug,
            'reference_code' => $q->reference_code,
            'title' => $q->title,
            'status' => $q->status->value,
            'parent_category' => $parent?->name,
            'subcategory' => $cat?->name,
            'category' => $cat?->name,
            'state' => $q->stateModel?->name,
            'city' => $q->city,
            'budget_minor' => (int) ($q->budget_amount_minor ?? 0),
            'cover_url' => $q->displayCoverUrl(),
            'updated_at' => $q->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'published_at' => $q->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'proposals_count' => (int) ($q->offers_count ?? 0),
            'client_edit_until' => $q->client_edit_until?->timezone('Africa/Lagos')->toIso8601String(),
            'can_client_edit' => $user !== null && Gate::forUser($user)->allows('update', $q),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function questCardPayload(Quest $q): array
    {
        return [
            'uuid' => $q->uuid,
            'slug' => $q->slug,
            'title' => $q->title,
            'category' => $q->questCategory?->name,
            'state' => $q->stateModel?->name,
            'city' => $q->city,
            'budget_minor' => (int) ($q->budget_amount_minor ?? 0),
            'cover_url' => $q->displayCoverUrl(),
            'featured' => QuestBoost::query()
                ->where('quest_id', $q->id)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->latest('id')
                ->value('tier'),
            'is_boosted' => QuestBoost::query()
                ->where('quest_id', $q->id)
                ->activeNow()
                ->exists(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questCreateStatsHints(): array
    {
        $empty = [
            'by_category' => [],
            'global_budget' => null,
            'global_completion' => null,
        ];

        if (! Schema::hasTable('quests')) {
            return $empty;
        }

        return Cache::remember('quests:create-stats-hints', 300, function () use ($empty): array {
            try {
                $budgetRows = Quest::query()
                ->where('status', QuestStatus::Open)
                ->whereNotNull('quest_category_id')
                ->groupBy('quest_category_id')
                ->selectRaw('quest_category_id')
                ->selectRaw('COUNT(*) as sample_size')
                ->selectRaw('AVG(budget_amount_minor) as avg_minor')
                ->selectRaw('MIN(budget_amount_minor) as min_minor')
                ->selectRaw('MAX(budget_amount_minor) as max_minor')
                ->get();

            $budgetByCat = [];
            foreach ($budgetRows as $r) {
                $cid = (string) (int) $r->quest_category_id;
                $budgetByCat[$cid] = [
                    'sample_size' => (int) $r->sample_size,
                    'avg_minor' => (int) round((float) $r->avg_minor),
                    'min_minor' => (int) $r->min_minor,
                    'max_minor' => (int) $r->max_minor,
                ];
            }

            $complRows = Quest::query()
                ->where('status', QuestStatus::Open)
                ->whereNotNull('quest_category_id')
                ->where('estimated_completion_days', '>', 0)
                ->groupBy('quest_category_id')
                ->selectRaw('quest_category_id')
                ->selectRaw('COUNT(*) as sample_size')
                ->selectRaw('AVG(estimated_completion_days) as avg_days')
                ->selectRaw('MIN(estimated_completion_days) as min_days')
                ->selectRaw('MAX(estimated_completion_days) as max_days')
                ->get();

            $complByCat = [];
            foreach ($complRows as $r) {
                $cid = (string) (int) $r->quest_category_id;
                $complByCat[$cid] = [
                    'sample_size' => (int) $r->sample_size,
                    'avg_days' => round((float) $r->avg_days, 1),
                    'min_days' => (int) $r->min_days,
                    'max_days' => (int) $r->max_days,
                ];
            }

            $byCategory = [];
            foreach (array_unique(array_merge(array_keys($budgetByCat), array_keys($complByCat))) as $cid) {
                $byCategory[$cid] = [
                    'budget' => $budgetByCat[$cid] ?? null,
                    'completion' => $complByCat[$cid] ?? null,
                ];
            }

            $gBudget = Quest::query()
                ->where('status', QuestStatus::Open)
                ->whereNotNull('budget_amount_minor')
                ->selectRaw('COUNT(*) as sample_size')
                ->selectRaw('AVG(budget_amount_minor) as avg_minor')
                ->selectRaw('MIN(budget_amount_minor) as min_minor')
                ->selectRaw('MAX(budget_amount_minor) as max_minor')
                ->first();

            $gCompl = Quest::query()
                ->where('status', QuestStatus::Open)
                ->where('estimated_completion_days', '>', 0)
                ->selectRaw('COUNT(*) as sample_size')
                ->selectRaw('AVG(estimated_completion_days) as avg_days')
                ->selectRaw('MIN(estimated_completion_days) as min_days')
                ->selectRaw('MAX(estimated_completion_days) as max_days')
                ->first();

                return [
                    'by_category' => $byCategory,
                    'global_budget' => $gBudget && (int) $gBudget->sample_size > 0 ? [
                        'sample_size' => (int) $gBudget->sample_size,
                        'avg_minor' => (int) round((float) $gBudget->avg_minor),
                        'min_minor' => (int) $gBudget->min_minor,
                        'max_minor' => (int) $gBudget->max_minor,
                    ] : null,
                    'global_completion' => $gCompl && (int) $gCompl->sample_size > 0 ? [
                        'sample_size' => (int) $gCompl->sample_size,
                        'avg_days' => round((float) $gCompl->avg_days, 1),
                        'min_days' => (int) $gCompl->min_days,
                        'max_days' => (int) $gCompl->max_days,
                    ] : null,
                ];
            } catch (\Throwable) {
                return $empty;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function questDetailPayload(Quest $quest, ?User $viewer = null): array
    {
        $isStaff = $viewer && in_array($viewer->role?->slug, ['admin', 'super_admin'], true);
        $isOwner = $viewer && (int) $viewer->id === (int) $quest->client_id;
        $showInternalCodes = $isOwner || $isStaff;
        $activeBoost = QuestBoost::query()
            ->where('quest_id', $quest->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('id')
            ->first();

        $data = [
            'id' => $quest->id,
            'slug' => $quest->slug,
            'canonical_url' => route('quests.show', $quest, absolute: true),
            'meta_description' => Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $quest->description))), 160) ?: null,
            'visibility' => $quest->visibility?->value,
            'freelancer_location_pref' => $quest->freelancer_location_pref?->value,
            'availability_need' => $quest->availability_need?->value,
            'project_type' => $quest->project_type?->value,
            'estimated_hours' => $quest->estimated_hours,
            'team_size' => $quest->team_size?->value,
            'featured_boost' => $activeBoost ? [
                'tier' => $activeBoost->tier,
                'label' => __('Boosted'),
                'expires_at' => $activeBoost->ends_at?->timezone('Africa/Lagos')->toIso8601String(),
            ] : null,
            'auto_listing_expiry_days' => $quest->auto_listing_expiry_days,
            'listing_expires_at' => $quest->listing_expires_at?->timezone('Africa/Lagos')->toIso8601String(),
            'client_edit_until' => $quest->client_edit_until?->timezone('Africa/Lagos')->toIso8601String(),
            'is_client_edit_locked' => $quest->status === QuestStatus::Open
                && $quest->client_edit_until !== null
                && now()->greaterThan($quest->client_edit_until),
            'max_offers' => $quest->max_offers,
            'views_count' => (int) $quest->views_count,
            'offers_count' => (int) $quest->offers_count,
            'saves_count' => (int) $quest->saves_count,
            'traffic_source' => $showInternalCodes ? $quest->traffic_source : null,
            'traffic_utm' => $showInternalCodes ? $quest->traffic_utm : null,
            'estimated_delivery_date' => $quest->estimated_delivery_date?->toDateString(),
            'uuid' => $quest->uuid,
            'route_key' => $quest->getRouteKey(),
            'reference_code' => $quest->reference_code,
            'title' => $quest->title,
            'description' => $quest->description,
            'admin_notices' => $quest->visibleAdminQuestNotices->map(fn ($notice) => [
                'id' => $notice->id,
                'type' => $notice->type,
                'body' => $notice->body,
                'created_at' => $notice->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])->values()->all(),
            'cover_url' => $quest->displayCoverUrl(),
            'status' => $quest->status->value,
            'quality_gate_feedback' => $quest->quality_gate_feedback,
            'quality_gate_failed_at' => $quest->quality_gate_failed_at?->toIso8601String(),
            'budget_minor' => (int) ($quest->budget_amount_minor ?? 0),
            'start_timing' => $quest->start_timing?->value,
            'estimated_completion_days' => $quest->estimated_completion_days,
            'site_visits_allowed' => (bool) $quest->site_visits_allowed,
            'site_access_level' => $quest->site_access_level,
            'pets_on_site' => $quest->pets_on_site,
            'pets_detail' => $quest->pets_detail,
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
                'role_slug' => $quest->client?->role?->slug,
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

        if (! $showInternalCodes) {
            unset($data['reference_code']);
            if (($quest->slug ?? '') !== '') {
                unset($data['uuid']);
            }
        }

        if ($isOwner && $viewer) {
            $data = array_merge($data, app(QuestListingExpiryService::class)->clientPayload($quest, $viewer));
        }

        return $data;
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
            ->where('is_active', true)
            ->where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->where('status', 'active')->orderBy('sort_order')->orderBy('name')])
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

    /**
     * @return list<array<string, mixed>>
     */
    protected function clientProposalSummariesForQuest(Quest $quest): array
    {
        return QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->visibleInClientInbox()
            ->with(['freelancer:id,first_name,last_name,name,slug,avatar_url,headline'])
            ->latest('created_at')
            ->limit(120)
            ->get()
            ->map(fn (QuestOffer $o) => QuestClientProposalsController::proposalRow($quest, $o))
            ->values()
            ->all();
    }
}
