<?php

namespace App\Services;

use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Centralises freelancer readiness for offers, matching, and (future) withdrawals.
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
     *   active_offer_count: int,
     *   limited_slots_remaining: int,
     *   can_submit_offers: bool,
     *   can_submit_limited_only: bool,
     *   withdrawal_ready: bool,
     *   blockers: list<array{code: string, message: string}>,
     *   hints: list<array{code: string, message: string}>,
     *   reminder_worthy: bool,
     * }
     */
    public function summarize(User $user): array
    {
        $user->loadMissing(['role', 'questCategoryPreferences']);

        if ($user->role?->slug !== 'freelancer') {
            return $this->emptySummary();
        }

        $leafCount = $this->leafCategoryCount($user);
        $addressOk = $this->hasStructuredAddress($user);
        $identityOk = $this->hasApprovedIdentity($user);

        $activeOffers = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
            ->count();

        $maxLimited = (int) config('freelancer_workspace.limited_offer_max_count', 3);
        $limitedSlots = max(0, $maxLimited - $activeOffers);

        $blockers = [];
        $hints = [];

        if ($leafCount < 1) {
            $blockers[] = [
                'code' => 'categories_missing',
                'message' => __('Choose at least one work subcategory in your account so we can match you to the right quests.'),
            ];
        }

        if (! $addressOk) {
            $blockers[] = [
                'code' => 'address_incomplete',
                'message' => __('Add your full address, state, LGA, and city on your account — clients and escrow checks rely on it.'),
            ];
        }

        if (! $identityOk) {
            $hints[] = [
                'code' => 'identity_pending',
                'message' => __('Verify your government ID under Trust & verifications to unlock unlimited offers and withdrawals.'),
            ];
        }

        if ($leafCount >= 1 && $addressOk && ($user->headline === null || trim((string) $user->headline) === '')) {
            $hints[] = [
                'code' => 'headline_recommended',
                'message' => __('Add a short headline — it helps clients understand your niche at a glance.'),
            ];
        }

        $tier = 'none';
        if ($leafCount >= 1 && $addressOk) {
            $tier = $identityOk ? 'full' : 'limited';
        }

        $canSubmit = $tier === 'full' || ($tier === 'limited' && $limitedSlots > 0);

        $withdrawalReady = (bool) config('freelancer_workspace.withdrawal_requires_identity', true)
            ? ($addressOk && $identityOk)
            : $addressOk;

        $reminderWorthy = count($blockers) > 0 || ! $identityOk;

        return [
            'tier' => $tier,
            'leaf_category_count' => $leafCount,
            'address_complete' => $addressOk,
            'identity_approved' => $identityOk,
            'active_offer_count' => $activeOffers,
            'limited_slots_remaining' => $limitedSlots,
            'can_submit_offers' => $canSubmit,
            'can_submit_limited_only' => $tier === 'limited' && $limitedSlots > 0,
            'withdrawal_ready' => $withdrawalReady,
            'blockers' => $blockers,
            'hints' => $hints,
            'reminder_worthy' => $reminderWorthy,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSubmitOffer(User $user, Quest $quest, ?int $quotedAmountMinor = null): void
    {
        if ($user->role?->slug !== 'freelancer') {
            throw ValidationException::withMessages([
                'offer' => [__('Only freelancer accounts can submit offers.')],
            ]);
        }

        if ($quest->client_id === $user->id) {
            throw ValidationException::withMessages([
                'offer' => [__('You cannot bid on your own quest.')],
            ]);
        }

        if ($quest->status !== QuestStatus::Open || $quest->freelancer_id !== null) {
            throw ValidationException::withMessages([
                'offer' => [__('This quest is not accepting new offers right now.')],
            ]);
        }

        $quest->loadMissing('visibility');
        if ($quest->visibility === QuestVisibility::Private) {
            throw ValidationException::withMessages([
                'offer' => [__('This quest is private and does not accept marketplace proposals.')],
            ]);
        }

        if ($quest->visibility === QuestVisibility::InviteOnly && ! $quest->isInvitedFreelancer($user)) {
            throw ValidationException::withMessages([
                'offer' => [__('Only freelancers invited by the client can propose on this quest.')],
            ]);
        }

        if ($quest->offers()->where('freelancer_id', $user->id)->whereIn('status', ['submitted', 'shortlisted', 'accepted'])->exists()) {
            throw ValidationException::withMessages([
                'offer' => [__('You already have an active offer on this quest.')],
            ]);
        }

        $maxOffers = $quest->max_offers;
        if ($maxOffers !== null) {
            $offerCount = $quest->offers()->whereIn('status', ['submitted', 'shortlisted', 'accepted'])->count();
            if ($offerCount >= (int) $maxOffers) {
                throw ValidationException::withMessages([
                    'offer' => [__('This quest has reached its maximum number of proposals.')],
                ]);
            }
        }

        $inviteOnlyInvited = $quest->visibility === QuestVisibility::InviteOnly && $quest->isInvitedFreelancer($user);

        if (! $inviteOnlyInvited && ! $this->freelancerPrefMatchesQuestCategory($user, $quest)) {
            throw ValidationException::withMessages([
                'offer' => [__('Add this quest’s work subcategory to your profile before you can send a proposal.')],
            ]);
        }

        $summary = $this->summarize($user);

        if (! $summary['can_submit_offers']) {
            $messages = array_map(fn (array $b) => $b['message'], $summary['blockers']);
            throw ValidationException::withMessages([
                'workspace' => $messages !== [] ? $messages : [__('Complete your freelancer profile before sending offers.')],
            ]);
        }

        if ($summary['tier'] === 'limited') {
            $maxBudget = (int) config('freelancer_workspace.limited_offer_max_budget_minor', 2_000_000);
            $budgetMinor = (int) ($quest->budget_amount_minor ?? 0);
            if ($budgetMinor > $maxBudget) {
                throw ValidationException::withMessages([
                    'offer' => [
                        __('Until your ID is approved, you can only bid on quests up to :amount.', [
                            'amount' => '₦'.number_format($maxBudget / 100, 0, '.', ','),
                        ]),
                    ],
                ]);
            }

            if ($quotedAmountMinor !== null && $quotedAmountMinor > $maxBudget) {
                throw ValidationException::withMessages([
                    'quoted_amount_minor' => [
                        __('Until your ID is approved, quoted amounts cannot exceed :amount.', [
                            'amount' => '₦'.number_format($maxBudget / 100, 0, '.', ','),
                        ]),
                    ],
                ]);
            }

            if ($summary['limited_slots_remaining'] <= 0) {
                throw ValidationException::withMessages([
                    'offer' => [
                        __('You have reached the temporary offer limit. Complete ID verification to send more offers.'),
                    ],
                ]);
            }
        }
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
            'active_offer_count' => 0,
            'limited_slots_remaining' => 0,
            'can_submit_offers' => false,
            'can_submit_limited_only' => false,
            'withdrawal_ready' => false,
            'blockers' => [],
            'hints' => [],
            'reminder_worthy' => false,
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
}
