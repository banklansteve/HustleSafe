<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Enums\AdminQuestStatus;
use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Verification\VerificationEngineService;
use App\Services\Verification\UserVerificationCatalogService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Centralises freelancer readiness for proposals, matching, and (future) withdrawals.
 *
 * Subcategories are stored as leaf rows on `quest_categories` (parent_id set).
 * Freelancers may attach multiple leaf categories — this widens quest matching.
 *
 * Separate "skills" taxonomy is not required: categories + profile copy are the
 * primary matching inputs; headline/bio remain strongly recommended in UI copy.
 */
class FreelancerWorkspaceReadinessService
{
    /**
     * @return array{
     *   tier: 'full'|'limited'|'none',
     *   leaf_category_count: int,
     *   address_complete: bool,
     *   identity_approved: bool,
     *   live_presence_approved: bool,
     *   high_value_quest_budget_minor: int,
     *   active_offer_count: int,
     *   limited_slots_remaining: int,
     *   can_submit_proposals: bool,
     *   can_submit_offers: bool,
     *   can_submit_limited_only: bool,
     *   withdrawal_ready: bool,
     *   blockers: list<array{code: string, message: string, action_label?: string, action_url?: string}>,
     *   hints: list<array{code: string, message: string, action_label?: string, action_url?: string}>,
     *   reminder_worthy: bool,
     *   profile_completion_percent: int,
     *   min_profile_completion_for_proposals: int,
     *   profile_ready_for_proposals: bool,
     * }
     */
    public function summarize(User $user): array
    {
        $user->loadMissing(['role', 'questCategoryPreferences']);

        if ($user->role?->slug !== 'freelancer') {
            return $this->emptySummary();
        }

        $minProfile = max(0, min(100, (int) config('freelancer_workspace.min_profile_completion_for_proposals', 55)));
        $profileCompletion = (int) ($user->profile_completion_percent ?? 0);
        $profileReadyForProposals = $profileCompletion >= $minProfile;

        $leafCount = $this->leafCategoryCount($user);
        $addressOk = $this->hasStructuredAddress($user);
        $identityOk = $this->hasApprovedIdentity($user);
        $livePresenceOk = $this->hasApprovedLivePresence($user);
        $verification = app(VerificationEngineService::class);
        $proposalLimitMinor = $verification->freelancerProposalLimitMinor($user);
        $effectiveLevel = $verification->effectiveLevel($user);

        $activeOffers = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
            ->count();

        $allBlockers = [];
        $allHints = [];

        if ($leafCount < 1) {
            $allBlockers[] = [
                'code' => 'categories_missing',
                'message' => __('Choose at least one work subcategory in your account so we can match you to the right quests.'),
                'action_label' => __('Pick subcategories'),
                'action_url' => route('account.show', ['tab' => 'overview']).'#account-work-categories',
            ];
        }

        if (! $addressOk) {
            $allBlockers[] = [
                'code' => 'address_incomplete',
                'message' => __('Add your full address, state, LGA, and city on your account — clients and escrow checks rely on it.'),
                'action_label' => __('Add address & LGA'),
                'action_url' => route('account.show', ['tab' => 'overview']).'#account-structured-address',
            ];
        }

        if (! $identityOk) {
            $allHints[] = [
                'code' => 'identity_pending',
                'message' => __('Complete your verification checks under Trust & verifications to unlock higher-value proposals and withdrawals.'),
                'action_label' => __('Submit ID'),
                'action_url' => route('verifications.index').'#verification-submit',
            ];
        } elseif (! $livePresenceOk && $this->freelancerEligibleForLivePresenceNudge($user)) {
            $allHints[] = [
                'code' => 'live_presence_recommended',
                'message' => __('Complete the selfie + ID check under Verifications to strengthen trust on high-value quests.'),
                'action_label' => __('Selfie + ID'),
                'action_url' => route('verifications.index').'#verification-submit',
            ];
        }

        if ($leafCount >= 1 && $addressOk && ($user->headline === null || trim((string) $user->headline) === '')) {
            $allHints[] = [
                'code' => 'headline_recommended',
                'message' => __('Add a short headline — it helps clients understand your niche at a glance.'),
                'action_label' => __('Add headline'),
                'action_url' => route('account.show', ['tab' => 'overview']).'#account-profile-story',
            ];
        }

        if ($leafCount >= 1 && $addressOk && ! $profileReadyForProposals) {
            $allBlockers[] = [
                'code' => 'profile_strength_low',
                'message' => __('Raise your profile strength to at least :percent% (headline, bio, categories, and trust signals) before sending proposals.', [
                    'percent' => $minProfile,
                ]),
                'action_label' => __('Improve profile'),
                'action_url' => route('account.show', ['tab' => 'overview']).'#account-profile-story',
            ];
        }

        if ($proposalLimitMinor <= 0) {
            $allBlockers[] = [
                'code' => 'verification_level_blocked',
                'message' => __('Your current verification level cannot submit proposals yet. Complete the required checks in Trust & verifications.'),
                'action_label' => __('Open verifications'),
                'action_url' => route('verifications.index').'#verification-submit',
            ];
        }

        $prioritized = $this->prioritizeWorkspaceActions(
            $user,
            $allBlockers,
            $allHints,
            $proposalLimitMinor,
            $profileReadyForProposals,
            $identityOk,
        );

        $blockers = $prioritized['blockers'];
        $hints = $prioritized['hints'];

        $tier = 'none';
        if ($leafCount >= 1 && $addressOk) {
            $tier = $proposalLimitMinor > 0 ? 'full' : 'none';
        }

        $canSubmit = $tier === 'full' && $profileReadyForProposals;

        $withdrawalReady = (bool) config('freelancer_workspace.withdrawal_requires_identity', true)
            ? ($addressOk && $identityOk)
            : $addressOk;

        $reminderWorthy = count($blockers) > 0 || ! $identityOk || ($identityOk && ! $livePresenceOk);

        return [
            'tier' => $tier,
            'leaf_category_count' => $leafCount,
            'address_complete' => $addressOk,
            'identity_approved' => $identityOk,
            'live_presence_approved' => $livePresenceOk,
            'high_value_quest_budget_minor' => $proposalLimitMinor,
            'verification_effective_level' => $effectiveLevel,
            'verification_proposal_limit_minor' => $proposalLimitMinor,
            'active_offer_count' => $activeOffers,
            'limited_slots_remaining' => 0,
            'can_submit_proposals' => $canSubmit,
            'can_submit_offers' => $canSubmit,
            'can_submit_limited_only' => false,
            'withdrawal_ready' => $withdrawalReady,
            'blockers' => $blockers,
            'hints' => $hints,
            'reminder_worthy' => $reminderWorthy,
            'profile_completion_percent' => $profileCompletion,
            'min_profile_completion_for_proposals' => $minProfile,
            'profile_ready_for_proposals' => $profileReadyForProposals,
        ];
    }

    /**
     * In-app messaging before a proposal: requires structured basics (not full proposal gates).
     */
    public function freelancerMayUseQuestMessaging(User $user, Quest $quest): bool
    {
        $user->loadMissing('role');
        if ($user->role?->slug !== 'freelancer' || (int) $quest->client_id === (int) $user->id) {
            return false;
        }

        $summary = $this->summarize($user);

        return $summary['leaf_category_count'] >= 1 && $summary['address_complete'];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSubmitOffer(User $user, Quest $quest, ?int $quotedAmountMinor = null): void
    {
        app(\App\Services\Onboarding\OnboardingPostingGateService::class)->assertCanPost($user, 'proposal');

        if ($user->role?->slug !== 'freelancer') {
            throw ValidationException::withMessages([
                'proposal' => [__('Only freelancer accounts can submit proposals.')],
            ]);
        }

        if ($quest->client_id === $user->id) {
            throw ValidationException::withMessages([
                'proposal' => [__('You cannot bid on your own quest.')],
            ]);
        }

        if ($quest->status !== QuestStatus::Open || $quest->freelancer_id !== null) {
            throw ValidationException::withMessages([
                'proposal' => [__('This quest is not accepting new proposals right now.')],
            ]);
        }

        if (in_array($quest->admin_status?->value ?? (string) $quest->admin_status, [AdminQuestStatus::Restricted->value, AdminQuestStatus::Suspended->value], true)) {
            throw ValidationException::withMessages([
                'proposal' => [__('This Quest is temporarily restricted by HustleSafe moderation and is not accepting new proposals.')],
            ]);
        }

        if ($quest->visibility === QuestVisibility::Private) {
            throw ValidationException::withMessages([
                'proposal' => [__('This quest is private and does not accept marketplace proposals.')],
            ]);
        }

        if ($quest->visibility === QuestVisibility::InviteOnly && ! $quest->isInvitedFreelancer($user)) {
            $isPro = app(\App\Services\Freelancer\FreelancerProSubscriptionService::class)->isPro($user);
            if (! $isPro) {
                throw ValidationException::withMessages([
                    'offer' => [__('Only freelancers invited by the client can propose on this quest. Pro members get early access to invite-only quests.')],
                ]);
            }
        }

        if ($quest->offers()->where('freelancer_id', $user->id)->whereIn('status', ['submitted', 'shortlisted', 'accepted'])->exists()) {
            throw ValidationException::withMessages([
                'proposal' => [__('You already have an active proposal on this quest.')],
            ]);
        }

        $maxOffers = $quest->max_offers;
        if ($maxOffers !== null) {
            $offerCount = $quest->offers()->whereIn('status', ['submitted', 'shortlisted', 'accepted'])->count();
            if ($offerCount >= (int) $maxOffers) {
                throw ValidationException::withMessages([
                    'proposal' => [__('This quest has reached its maximum number of proposals.')],
                ]);
            }
        }

        $inviteOnlyInvited = $quest->visibility === QuestVisibility::InviteOnly && $quest->isInvitedFreelancer($user);

        if (! $inviteOnlyInvited && ! $this->freelancerPrefMatchesQuestCategory($user, $quest)) {
            throw ValidationException::withMessages([
                'proposal' => [__('Add this quest’s work subcategory to your profile before you can send a proposal.')],
            ]);
        }

        $summary = $this->summarize($user);

        if (! $summary['can_submit_proposals']) {
            $messages = array_map(fn (array $b) => $b['message'], $summary['blockers']);
            throw ValidationException::withMessages([
                'proposal' => $messages !== [] ? $messages : [__('Complete your freelancer profile before sending proposals.')],
            ]);
        }

        app(VerificationEngineService::class)->assertFreelancerCanPropose($user, $quest, $quotedAmountMinor);
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProps(User $user): array
    {
        if ($user->role?->slug !== 'freelancer') {
            return ['enabled' => false];
        }

        return array_merge(['enabled' => true], $this->summarize($user));
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySummary(): array
    {
        return [
            'tier' => 'none',
            'leaf_category_count' => 0,
            'address_complete' => false,
            'identity_approved' => false,
            'live_presence_approved' => false,
            'high_value_quest_budget_minor' => 0,
            'verification_effective_level' => 0,
            'verification_proposal_limit_minor' => 0,
            'active_offer_count' => 0,
            'limited_slots_remaining' => 0,
            'can_submit_proposals' => false,
            'can_submit_offers' => false,
            'can_submit_limited_only' => false,
            'withdrawal_ready' => false,
            'blockers' => [],
            'hints' => [],
            'reminder_worthy' => false,
            'profile_completion_percent' => 0,
            'min_profile_completion_for_proposals' => (int) config('freelancer_workspace.min_profile_completion_for_proposals', 55),
            'profile_ready_for_proposals' => false,
        ];
    }

    public function matchesQuestCategory(User $user, Quest $quest): bool
    {
        return $this->freelancerPrefMatchesQuestCategory($user, $quest);
    }

    protected function freelancerPrefMatchesQuestCategory(User $user, Quest $quest): bool
    {
        $quest->loadMissing('questCategory');
        $cid = (int) ($quest->quest_category_id ?? 0);
        if ($cid < 1) {
            return false;
        }

        return $user->questCategoryPreferences()->where('quest_categories.id', $cid)->exists();
    }

    protected function leafCategoryCount(User $user): int
    {
        return (int) $user->questCategoryPreferences()
            ->whereNotNull('quest_categories.parent_id')
            ->count();
    }

    protected function hasStructuredAddress(User $user): bool
    {
        $v = Validator::make(
            [
                'address_line' => $user->address_line,
                'city' => $user->city,
                'state_id' => $user->state_id,
                'local_government_id' => $user->local_government_id,
            ],
            [
                'address_line' => ['required', 'string', 'min:8'],
                'city' => ['required', 'string', 'min:2'],
                'state_id' => ['required', 'integer', 'min:1'],
                'local_government_id' => ['required', 'integer', 'min:1'],
            ]
        );

        return ! $v->fails();
    }

    protected function hasApprovedIdentity(User $user): bool
    {
        return UserVerification::query()
            ->where('user_id', $user->id)
            ->where('category', UserVerificationCategory::Identity)
            ->where('status', UserVerificationStatus::Approved)
            ->exists();
    }

    protected function hasApprovedLivePresence(User $user): bool
    {
        return UserVerification::query()
            ->where('user_id', $user->id)
            ->where('category', UserVerificationCategory::LivePresence)
            ->where('status', UserVerificationStatus::Approved)
            ->exists();
    }

    protected function freelancerEligibleForLivePresenceNudge(User $user): bool
    {
        $user->loadMissing('role');
        if ($user->role?->slug !== 'freelancer') {
            return false;
        }

        $engine = app(VerificationEngineService::class);

        return $engine->accountAgeDaysRemaining($user, 90) <= 0;
    }

    /**
     * Surface at most two clear next actions — setup first, then verification, then profile polish.
     *
     * @param  list<array{code: string, message: string, action_label?: string, action_url?: string}>  $allBlockers
     * @param  list<array{code: string, message: string, action_label?: string, action_url?: string}>  $allHints
     * @return array{blockers: list<array<string, mixed>>, hints: list<array<string, mixed>>}
     */
    protected function prioritizeWorkspaceActions(
        User $user,
        array $allBlockers,
        array $allHints,
        int $proposalLimitMinor,
        bool $profileReadyForProposals,
        bool $identityApproved,
    ): array {
        $byCode = collect(array_merge($allBlockers, $allHints))->keyBy('code');
        $blockers = [];
        $hints = [];
        $maxTotal = 2;

        foreach (['categories_missing', 'address_incomplete'] as $code) {
            if ($byCode->has($code)) {
                $blockers[] = $byCode->get($code);

                return ['blockers' => $blockers, 'hints' => []];
            }
        }

        $verificationAction = $this->verificationNextAction($user, $proposalLimitMinor, $identityApproved);
        if ($verificationAction !== null) {
            if ($proposalLimitMinor <= 0 || ! $identityApproved) {
                $blockers[] = $verificationAction;
            } else {
                $hints[] = $verificationAction;
            }
        } elseif ($byCode->has('verification_level_blocked')) {
            $blockers[] = $byCode->get('verification_level_blocked');
        }

        $remaining = $maxTotal - count($blockers) - count($hints);
        $verificationBlocking = $proposalLimitMinor <= 0 || ! $identityApproved;
        if ($remaining > 0 && ! $profileReadyForProposals && $byCode->has('profile_strength_low') && ! $verificationBlocking) {
            $blockers[] = $byCode->get('profile_strength_low');
            $remaining--;
        }

        $verificationFocused = collect($blockers)->contains(fn ($item) => ($item['code'] ?? '') === 'verification_next')
            || collect($hints)->contains(fn ($item) => ($item['code'] ?? '') === 'verification_next');

        if ($remaining > 0) {
            foreach (['headline_recommended', 'live_presence_recommended', 'identity_pending'] as $code) {
                if ($remaining <= 0) {
                    break;
                }
                if ($verificationFocused && in_array($code, ['identity_pending', 'live_presence_recommended'], true)) {
                    continue;
                }
                if ($byCode->has($code) && ! collect($hints)->contains('code', $code) && ! collect($blockers)->contains('code', $code)) {
                    $hints[] = $byCode->get($code);
                    $remaining--;
                }
            }
        }

        return [
            'blockers' => array_slice($blockers, 0, $maxTotal),
            'hints' => array_slice($hints, 0, max(0, $maxTotal - count($blockers))),
        ];
    }

    /**
     * @return array{code: string, message: string, action_label: string, action_url: string}|null
     */
    protected function verificationNextAction(User $user, int $proposalLimitMinor, bool $identityApproved): ?array
    {
        $catalog = app(UserVerificationCatalogService::class)->forUser($user);
        $next = $catalog['next_step'] ?? null;

        if (! is_array($next) || ($next['status'] ?? '') === 'complete') {
            return null;
        }

        if (in_array($next['key'] ?? '', ['live_presence', 'account_age'], true)
            && in_array($next['status'] ?? '', ['locked', 'waiting'], true)
            && app(VerificationEngineService::class)->accountAgeDaysRemaining($user, 90) > 0) {
            return null;
        }

        $title = (string) ($next['title'] ?? __('Trust & verifications'));
        $stageMessage = (string) ($next['info_bar'] ?? $next['message'] ?? '');

        if ($proposalLimitMinor <= 0) {
            $message = __('Your current verification level cannot submit proposals yet. Complete the required checks in Trust & verifications.');
            if ($stageMessage !== '') {
                $message .= ' '.$stageMessage;
            }
        } elseif (! $identityApproved) {
            $message = $stageMessage !== ''
                ? $stageMessage
                : __('Complete identity and address verification under Trust & verifications to unlock proposals.');
        } else {
            $message = $stageMessage !== ''
                ? $stageMessage
                : __('Complete your next verification step under Trust & verifications.');
        }

        return [
            'code' => 'verification_next',
            'message' => trim($message),
            'action_label' => $title,
            'action_url' => route('verifications.index').'#verification-submit',
        ];
    }
}
