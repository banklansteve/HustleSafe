<?php

namespace App\Services\Verification;

use App\Enums\QuestStatus;
use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\KycSetting;
use App\Models\Quest;
use App\Models\QuestArbitrationAgreement;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\VerificationAnomalyFlag;
use App\Models\VerificationEngineAuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificationEngineService
{
    public const LEVEL_MIN = 0;
    public const CLIENT_LEVEL_MAX = 5;
    public const FREELANCER_LEVEL_MAX = 6;
    /** @deprecated Use role-specific max level helpers */
    public const LEVEL_MAX = self::FREELANCER_LEVEL_MAX;

    public function maxLevelFor(User $user): int
    {
        return $this->maxLevelForRole($this->isFreelancer($user));
    }

    public function maxLevelForRole(bool $isFreelancer): int
    {
        $requirements = $isFreelancer ? $this->freelancerLevelRequirements() : $this->clientLevelRequirements();

        return $this->maxLevelFromRequirements(is_array($requirements) ? $requirements : []);
    }

    /**
     * @param  array<int|string, mixed>  $requirements
     */
    public function maxLevelFromRequirements(array $requirements): int
    {
        if ($requirements === []) {
            return self::LEVEL_MIN;
        }

        return max(self::LEVEL_MIN, ...array_map('intval', array_keys($requirements)));
    }

    /**
     * Highest client quest posting limit configured in Verification Engine → Limits.
     */
    public function platformMaxQuestBudgetMinor(): int
    {
        $map = Arr::get($this->limits(), 'client_posting_minor', []);

        return $this->maxFromLevelMap(is_array($map) ? $map : []);
    }

    /**
     * Highest freelancer proposal value limit configured in Verification Engine → Limits.
     */
    public function platformMaxProposalValueMinor(): int
    {
        $map = Arr::get($this->limits(), 'freelancer_proposal_minor', []);

        return $this->maxFromLevelMap(is_array($map) ? $map : []);
    }

    public function platformMaxProposalValueNgn(): int
    {
        return (int) floor($this->platformMaxProposalValueMinor() / 100);
    }

    public function minQuestBudgetMinor(): int
    {
        return max(1, (int) ($this->safeguards()['min_quest_budget_minor'] ?? 10_000));
    }

    /**
     * @param  array<int|string, int>  $map
     */
    private function maxFromLevelMap(array $map): int
    {
        if ($map === []) {
            return 0;
        }

        return max(array_map(fn ($value) => (int) $value, array_values($map)));
    }

    public function setting(string $key, mixed $fallback = null): mixed
    {
        if (Schema::hasTable('kyc_settings')) {
            $value = KycSetting::value($key);
            if ($value !== null) {
                return $value;
            }
        }

        return $fallback ?? config("verification_engine.{$key}");
    }

    public function types(): array
    {
        return $this->setting('verification_types', config('verification_engine.types', []));
    }

    public function levelRequirements(): array
    {
        return $this->clientLevelRequirements();
    }

    public function clientLevelRequirements(): array
    {
        return $this->mergeLevelRequirements(
            config('verification_engine.client_levels', config('verification_engine.levels', [])),
            $this->setting('verification_client_level_requirements', null),
        );
    }

    public function freelancerLevelRequirements(): array
    {
        return $this->mergeLevelRequirements(
            config('verification_engine.freelancer_levels', []),
            $this->setting('verification_freelancer_level_requirements', null),
        );
    }

    public function stageContent(): array
    {
        $defaults = config('verification_engine.stage_content', []);
        $stored = $this->setting('verification_stage_content', null);

        if (! is_array($stored)) {
            return is_array($defaults) ? $defaults : [];
        }

        return $this->mergeStageContent(is_array($defaults) ? $defaults : [], $stored);
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>>>  $defaults
     * @param  array<string, array<int|string, array<string, mixed>>>  $stored
     * @return array<string, array<int|string, array<string, mixed>>>
     */
    public function mergeStageContent(array $defaults, array $stored): array
    {
        $merged = $defaults;

        foreach (['client', 'freelancer'] as $role) {
            foreach ($stored[$role] ?? [] as $level => $content) {
                if (! is_array($content)) {
                    continue;
                }

                $levelKey = is_numeric($level) ? (int) $level : $level;
                $existing = is_array($merged[$role][$levelKey] ?? null) ? $merged[$role][$levelKey] : [];
                $overrides = array_filter($content, fn ($value) => is_string($value) && trim($value) !== '');

                $merged[$role][$levelKey] = array_merge($existing, $overrides);
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, array<int|string, mixed>>  $stageContent
     * @return array<string, array<int, array{title: string, message: string, info_bar: string}>>
     */
    public function normalizeStageContentPayload(array $stageContent): array
    {
        $normalized = [];

        foreach (['client', 'freelancer'] as $role) {
            $normalized[$role] = [];

            foreach ($stageContent[$role] ?? [] as $level => $content) {
                if (! is_array($content)) {
                    continue;
                }

                $normalized[$role][(int) $level] = [
                    'title' => trim((string) ($content['title'] ?? '')),
                    'message' => trim((string) ($content['message'] ?? '')),
                    'info_bar' => trim((string) ($content['info_bar'] ?? '')),
                ];
            }
        }

        return $normalized;
    }

    public function levelRequirementsFor(User $user): array
    {
        return $this->isFreelancer($user) ? $this->freelancerLevelRequirements() : $this->clientLevelRequirements();
    }

    public function isFreelancer(User $user): bool
    {
        return $user->usesFreelancerVerificationLimits();
    }

    /**
     * @return array{title?: string, message?: string, info_bar?: string}
     */
    public function stageContentFor(User $user, int $level): array
    {
        $role = $this->isFreelancer($user) ? 'freelancer' : 'client';
        $defaults = Arr::get(config('verification_engine.stage_content', []), "{$role}.{$level}", []);
        $stored = Arr::get($this->stageContent(), "{$role}.{$level}", []);
        $merged = array_merge(
            is_array($defaults) ? $defaults : [],
            is_array($stored) ? array_filter($stored, fn ($v) => is_string($v) && trim($v) !== '') : [],
        );

        return $this->interpolateAccountAgeStageContent(
            $user,
            $level,
            $merged,
        );
    }

    /**
     * Lowest configured tier whose requirements introduce a verification check.
     */
    public function targetLevelForRequirement(User $user, string $requirement): ?int
    {
        foreach (range(self::LEVEL_MIN, $this->maxLevelFor($user)) as $level) {
            $requirements = Arr::get($this->levelRequirementsFor($user), "{$level}.requirements", []);
            if (! is_array($requirements)) {
                continue;
            }

            if ($this->requirementPresentInLevel($requirements, $requirement)) {
                return $level;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $requirements
     */
    private function requirementPresentInLevel(array $requirements, string $needle): bool
    {
        foreach ($requirements as $requirement) {
            if (is_string($requirement) && $requirement === $needle) {
                return true;
            }

            if (is_array($requirement) && isset($requirement['any_of']) && in_array($needle, $requirement['any_of'], true)) {
                return true;
            }
        }

        return false;
    }

    public function limitAtLevel(User $user, int $level): int
    {
        $key = $this->isFreelancer($user) ? 'freelancer_proposal_minor' : 'client_posting_minor';
        $map = Arr::get($this->limits(), $key, []);

        return $this->limitFromLevelMap(is_array($map) ? $map : [], $level);
    }

    /**
     * Limit for the user's current stored verification level (UI display).
     */
    public function displayClientPostingLimitMinor(User $user): int
    {
        return $this->clientPostingLimitMinor($user);
    }

    /**
     * Limit for the user's current stored verification level (UI display).
     */
    public function displayFreelancerProposalLimitMinor(User $user): int
    {
        return $this->freelancerProposalLimitMinor($user);
    }

    /**
     * @param  array<int|string, int>  $map
     */
    private function limitFromLevelMap(array $map, int $level): int
    {
        return (int) ($map[$level] ?? $map[(string) $level] ?? 0);
    }

    public function formatMoneyMinor(int $minor): string
    {
        return '₦'.number_format($minor / 100, 0);
    }

    public function limits(): array
    {
        $defaults = config('verification_engine.limits', []);
        $stored = $this->setting('verification_limits', null);

        if (! is_array($stored)) {
            return is_array($defaults) ? $defaults : [];
        }

        return [
            'client_posting_minor' => $this->mergeLimitLevelMap(
                Arr::get($defaults, 'client_posting_minor', []),
                Arr::get($stored, 'client_posting_minor', []),
            ),
            'freelancer_proposal_minor' => $this->mergeLimitLevelMap(
                Arr::get($defaults, 'freelancer_proposal_minor', []),
                Arr::get($stored, 'freelancer_proposal_minor', []),
            ),
            'freelancer_monthly_proposals' => $this->mergeLimitLevelMap(
                Arr::get($defaults, 'freelancer_monthly_proposals', []),
                Arr::get($stored, 'freelancer_monthly_proposals', []),
            ),
        ];
    }

    /**
     * @return array{client: list<array{level: int, label: string, limit_minor: int, limit_formatted: string}>, freelancer: list<array{level: int, label: string, limit_minor: int, limit_formatted: string}>}
     */
    public function tierCatalog(): array
    {
        return [
            'client' => $this->tierCatalogForRole(false),
            'freelancer' => $this->tierCatalogForRole(true),
        ];
    }

    /**
     * @return list<array{level: int, label: string, limit_minor: int, limit_formatted: string}>
     */
    public function tierCatalogForRole(bool $isFreelancer): array
    {
        $requirements = $isFreelancer ? $this->freelancerLevelRequirements() : $this->clientLevelRequirements();
        $limitKey = $isFreelancer ? 'freelancer_proposal_minor' : 'client_posting_minor';
        $limitMap = Arr::get($this->limits(), $limitKey, []);
        $catalog = [];

        foreach ($this->sortedLevelKeys(is_array($requirements) ? $requirements : []) as $level) {
            $config = $requirements[$level] ?? $requirements[(string) $level] ?? [];
            $limitMinor = $this->limitFromLevelMap(is_array($limitMap) ? $limitMap : [], $level);
            $catalog[] = [
                'level' => $level,
                'label' => is_array($config) ? (string) ($config['label'] ?? "L{$level}") : "L{$level}",
                'limit_minor' => $limitMinor,
                'limit_formatted' => $this->formatMoneyMinor($limitMinor),
            ];
        }

        return $catalog;
    }

    /**
     * @return array{id: int, name: string, email: string, role: ?string, current_level: int, earned_level: int, current_label: string, limit_minor: int, limit_formatted: string, tier_limit_minor: int, tier_limit_formatted: string, max_level: int, has_custom_limit: bool, tier_options: list<array{level: int, label: string, limit_minor: int, limit_formatted: string}>}
     */
    public function userOverrideContext(User $user): array
    {
        $isFreelancer = $this->isFreelancer($user);
        $level = $this->storedLevel($user);
        $limitMinor = $isFreelancer
            ? $this->freelancerProposalLimitMinor($user)
            : $this->clientPostingLimitMinor($user);
        $tierLimitMinor = $this->limitAtLevel($user, $level);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->slug ?? $user->account_type,
            'is_freelancer' => $isFreelancer,
            'limit_catalog' => $isFreelancer ? 'freelancer' : 'client',
            'current_level' => $level,
            'earned_level' => $this->earnedLevel($user),
            'current_label' => $this->levelLabel($level, $user),
            'limit_minor' => $limitMinor,
            'limit_formatted' => $this->formatMoneyMinor($limitMinor),
            'tier_limit_minor' => $tierLimitMinor,
            'tier_limit_formatted' => $this->formatMoneyMinor($tierLimitMinor),
            'max_level' => $this->maxLevelFor($user),
            'has_custom_limit' => $isFreelancer
                ? $user->custom_freelancer_proposal_limit_minor !== null
                : $user->custom_client_post_limit_minor !== null,
            'tier_options' => $this->tierCatalogForRole($isFreelancer),
        ];
    }

    public function safeguards(): array
    {
        return $this->setting('verification_safeguards', config('verification_engine.safeguards', []));
    }

    public function completedVerificationTypes(User $user): array
    {
        $completed = [];

        if ($user->hasVerifiedEmail()) {
            $completed[] = 'email';
        }

        $records = $user->relationLoaded('userVerifications')
            ? $user->userVerifications
            : $user->userVerifications()->get(['category', 'verification_type', 'status']);

        foreach ($records as $record) {
            $status = $record->status instanceof UserVerificationStatus ? $record->status : UserVerificationStatus::tryFrom((string) $record->status);
            if (! $status?->isVerified()) {
                continue;
            }

            $category = $record->category instanceof UserVerificationCategory
                ? $record->category
                : UserVerificationCategory::tryFrom((string) $record->category);

            $completed[] = $this->levelKeyForCategory($category, (string) ($record->verification_type ?: ''));
        }

        if (in_array('identity', $completed, true) && in_array('address', $completed, true)) {
            $completed[] = 'identity_address';
        }

        return array_values(array_unique(array_filter($completed)));
    }

    public function earnedLevel(User $user): int
    {
        return $this->levelFromRequirements($user, includeAccountAge: false);
    }

    /**
     * Verification level after document checks and account-age gates (unless overridden).
     */
    public function resolvedVerificationLevel(User $user): int
    {
        if ($user->verification_level_override !== null) {
            return max(self::LEVEL_MIN, min($this->maxLevelFor($user), (int) $user->verification_level_override));
        }

        return $this->levelFromRequirements($user, includeAccountAge: true);
    }

    /**
     * Highest tier whose financial limit applies (matches resolved level unless restricted/custom).
     */
    public function limitLevel(User $user): int
    {
        if ($user->verification_restricted_at !== null) {
            return 0;
        }

        return $this->resolvedVerificationLevel($user);
    }

    private function levelFromRequirements(User $user, bool $includeAccountAge): int
    {
        $completed = $this->completedVerificationTypes($user);
        $levels = $this->levelRequirementsFor($user);
        $resolved = 0;

        foreach (range(self::LEVEL_MIN, $this->maxLevelFor($user)) as $level) {
            $requirements = Arr::get($levels, "{$level}.requirements", []);
            if ($this->requirementsMet($requirements, $completed, $user, $includeAccountAge)) {
                $resolved = $level;
            }
        }

        return $resolved;
    }

    public function storedLevel(User $user): int
    {
        $stored = (int) ($user->current_verification_level ?? $user->kyc_tier ?? $user->verification_tier ?? 0);
        $override = $user->verification_level_override;

        return $override === null ? $stored : (int) $override;
    }

    /**
     * Verification badge level (document checks). Synced from earnedLevel via recalculate().
     */
    public function effectiveLevel(User $user): int
    {
        return $this->storedLevel($user);
    }

    public function recalculate(User $user, ?User $actor = null, ?string $reason = null): int
    {
        $old = [
            'current_verification_level' => (int) ($user->current_verification_level ?? $user->kyc_tier ?? $user->verification_tier ?? 0),
            'kyc_tier' => (int) ($user->kyc_tier ?? 0),
            'verification_tier' => (int) ($user->verification_tier ?? 0),
        ];
        $earned = $this->earnedLevel($user);
        $resolved = $this->resolvedVerificationLevel($user);
        $level = $user->verification_level_override !== null
            ? max(self::LEVEL_MIN, min($this->maxLevelFor($user), (int) $user->verification_level_override))
            : $resolved;

        $user->forceFill([
            'current_verification_level' => $level,
            'kyc_tier' => $level,
            'verification_tier' => $level,
            'kyc_status' => $earned > 0 ? 'verified' : 'unverified',
            'kyc_verified_at' => $earned > 0 ? ($user->kyc_verified_at ?? now()) : null,
        ])->saveQuietly();

        if ($old['current_verification_level'] !== $level) {
            $this->audit($actor, $user, 'verification_level.recalculated', $old, [
                'current_verification_level' => $level,
                'earned_level' => $earned,
            ], $reason);
        }

        return $level;
    }

    public function clientPostingLimitMinor(User $user): int
    {
        if ($user->verification_restricted_at !== null) {
            return 0;
        }
        if ($user->custom_client_post_limit_minor !== null) {
            return (int) $user->custom_client_post_limit_minor;
        }

        $map = Arr::get($this->limits(), 'client_posting_minor', []);

        return $this->limitFromLevelMap(is_array($map) ? $map : [], $this->limitLevel($user));
    }

    public function freelancerProposalLimitMinor(User $user): int
    {
        if ($user->verification_restricted_at !== null) {
            return 0;
        }
        if ($user->custom_freelancer_proposal_limit_minor !== null) {
            return (int) $user->custom_freelancer_proposal_limit_minor;
        }

        $map = Arr::get($this->limits(), 'freelancer_proposal_minor', []);

        return $this->limitFromLevelMap(is_array($map) ? $map : [], $this->limitLevel($user));
    }

    public function freelancerMonthlyProposalLimit(User $user): int
    {
        if ($user->verification_restricted_at !== null) {
            return 0;
        }

        $map = Arr::get($this->limits(), 'freelancer_monthly_proposals', []);

        return $this->limitFromLevelMap(is_array($map) ? $map : [], $this->limitLevel($user));
    }

    public function freelancerCanProposeForBudget(User $user, int $budgetMinor): bool
    {
        return ! $this->exceedsFreelancerProposalLimit($user, $budgetMinor);
    }

    public function exceedsClientPostingLimit(User $user, int $budgetMinor): bool
    {
        $limit = $this->clientPostingLimitMinor($user);

        return $budgetMinor > 0 && ($limit <= 0 || $budgetMinor > $limit);
    }

    public function exceedsFreelancerProposalLimit(User $user, int $valueMinor): bool
    {
        $limit = $this->freelancerProposalLimitMinor($user);

        return $valueMinor > 0 && ($limit <= 0 || $valueMinor > $limit);
    }

    /**
     * @return array{
     *     budget_minor: int,
     *     limit_minor: int,
     *     tier_limit_minor: int,
     *     verification_level: int,
     *     limit_level: int,
     *     verification_level_label: string,
     *     limit_level_label: string,
     *     limit_source: 'restricted'|'custom'|'tier',
     *     exceeds: bool,
     * }
     */
    public function clientPostingLimitAuditContext(User $user, int $budgetMinor): array
    {
        if ($user->verification_restricted_at !== null) {
            return [
                'budget_minor' => $budgetMinor,
                'limit_minor' => 0,
                'tier_limit_minor' => 0,
                'verification_level' => $this->resolvedVerificationLevel($user),
                'limit_level' => 0,
                'verification_level_label' => $this->levelLabel($this->resolvedVerificationLevel($user), $user),
                'limit_level_label' => $this->levelLabel(0, $user),
                'limit_source' => 'restricted',
                'exceeds' => $budgetMinor > 0,
            ];
        }

        $limitLevel = $this->limitLevel($user);
        $verificationLevel = $this->resolvedVerificationLevel($user);
        $limitMinor = $this->clientPostingLimitMinor($user);
        $tierLimitMinor = $this->limitAtLevel($user, $limitLevel);
        $limitSource = $user->custom_client_post_limit_minor !== null ? 'custom' : 'tier';

        return [
            'budget_minor' => $budgetMinor,
            'limit_minor' => $limitMinor,
            'tier_limit_minor' => $tierLimitMinor,
            'verification_level' => $verificationLevel,
            'limit_level' => $limitLevel,
            'verification_level_label' => $this->levelLabel($verificationLevel, $user),
            'limit_level_label' => $this->levelLabel($limitLevel, $user),
            'limit_source' => $limitSource,
            'exceeds' => $this->exceedsClientPostingLimit($user, $budgetMinor),
        ];
    }

    /**
     * @return array{
     *     value_minor: int,
     *     limit_minor: int,
     *     tier_limit_minor: int,
     *     verification_level: int,
     *     limit_level: int,
     *     verification_level_label: string,
     *     limit_level_label: string,
     *     limit_source: 'restricted'|'custom'|'tier',
     *     exceeds: bool,
     * }
     */
    public function freelancerProposalLimitAuditContext(User $user, int $valueMinor): array
    {
        if ($user->verification_restricted_at !== null) {
            return [
                'value_minor' => $valueMinor,
                'limit_minor' => 0,
                'tier_limit_minor' => 0,
                'verification_level' => $this->resolvedVerificationLevel($user),
                'limit_level' => 0,
                'verification_level_label' => $this->levelLabel($this->resolvedVerificationLevel($user), $user),
                'limit_level_label' => $this->levelLabel(0, $user),
                'limit_source' => 'restricted',
                'exceeds' => $valueMinor > 0,
            ];
        }

        $limitLevel = $this->limitLevel($user);
        $verificationLevel = $this->resolvedVerificationLevel($user);
        $limitMinor = $this->freelancerProposalLimitMinor($user);
        $tierLimitMinor = $this->limitAtLevel($user, $limitLevel);
        $limitSource = $user->custom_freelancer_proposal_limit_minor !== null ? 'custom' : 'tier';

        return [
            'value_minor' => $valueMinor,
            'limit_minor' => $limitMinor,
            'tier_limit_minor' => $tierLimitMinor,
            'verification_level' => $verificationLevel,
            'limit_level' => $limitLevel,
            'verification_level_label' => $this->levelLabel($verificationLevel, $user),
            'limit_level_label' => $this->levelLabel($limitLevel, $user),
            'limit_source' => $limitSource,
            'exceeds' => $this->exceedsFreelancerProposalLimit($user, $valueMinor),
        ];
    }

    public function assertClientCanPostQuest(User $user, int $budgetMinor): void
    {
        $limit = $this->clientPostingLimitMinor($user);
        if ($limit > 0 && $budgetMinor <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'budget_amount_minor' => $this->limitMessage($user, $limit, 'post quests'),
        ]);
    }

    public function assertFreelancerCanPropose(User $user, Quest $quest, ?int $quotedMinor = null): void
    {
        $value = max((int) ($quest->budget_amount_minor ?? 0), (int) ($quotedMinor ?? 0));
        $limit = $this->freelancerProposalLimitMinor($user);
        if ($limit > 0 && $value <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'proposal' => $this->limitMessage($user, $limit, 'submit proposals for this Quest value'),
        ]);
    }

    public function assertCanMoveToInProgress(Quest $quest, ?QuestOffer $offer = null): void
    {
        $value = max((int) ($quest->budget_amount_minor ?? 0), (int) ($offer?->quoted_amount_minor ?? 0));
        $safeguards = $this->safeguards();

        if ($value >= (int) ($safeguards['escrow_enforcement_threshold_minor'] ?? 100) && $quest->escrow_status !== 'funded') {
            throw ValidationException::withMessages(['escrow' => __('Escrow must be funded in full before this Quest can move to In Progress.')]);
        }

        $arbitration = (int) ($safeguards['high_value_arbitration_threshold_minor'] ?? 100_000_000);
        if ($value >= $arbitration && ! $this->hasBothArbitrationAgreements($quest, $offer)) {
            throw ValidationException::withMessages(['arbitration' => __('Both parties must accept platform-mediated arbitration before this high-value Quest can move to In Progress.')]);
        }
    }

    public function arbitrationRequired(Quest $quest, ?QuestOffer $offer = null): bool
    {
        $value = max((int) ($quest->budget_amount_minor ?? 0), (int) ($offer?->quoted_amount_minor ?? 0));

        return $value >= (int) ($this->safeguards()['high_value_arbitration_threshold_minor'] ?? 100_000_000);
    }

    public function hasBothArbitrationAgreements(Quest $quest, ?QuestOffer $offer): bool
    {
        if ($offer === null) {
            return false;
        }

        $parties = QuestArbitrationAgreement::query()
            ->where('quest_id', $quest->id)
            ->where('quest_offer_id', $offer->id)
            ->pluck('party')
            ->all();

        return in_array('client', $parties, true) && in_array('freelancer', $parties, true);
    }

    public function recordArbitrationAgreement(Quest $quest, QuestOffer $offer, User $user, string $party): void
    {
        QuestArbitrationAgreement::query()->updateOrCreate([
            'quest_id' => $quest->id,
            'quest_offer_id' => $offer->id,
            'user_id' => $user->id,
            'party' => $party,
        ], [
            'agreed_at' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => request() ? Str::limit((string) request()->userAgent(), 2000, '') : null,
        ]);
    }

    public function flagDuplicateQuestIfNeeded(Quest $quest): ?VerificationAnomalyFlag
    {
        $limit = (int) ($this->safeguards()['quest_repost_limit'] ?? 2);
        if ($limit < 1) {
            return null;
        }

        $similar = Quest::query()
            ->where('id', '<>', $quest->id)
            ->where('client_id', $quest->client_id)
            ->where('quest_category_id', $quest->quest_category_id)
            ->where('title', 'like', '%'.Str::substr($quest->title, 0, 40).'%')
            ->count();

        if ($similar < $limit) {
            return null;
        }

        return $this->createAnomalyFlag($quest->client, 'quest_repost_limit_exceeded', [
            'quest_id' => $quest->id,
            'similar_count' => $similar,
            'limit' => $limit,
        ], $quest);
    }

    public function runAnomalyChecks(User $user, ?Quest $quest = null, ?QuestOffer $offer = null): void
    {
        $s = $this->safeguards();
        $ageDays = $user->created_at?->diffInDays(now()) ?? 9999;
        $value = max((int) ($quest?->budget_amount_minor ?? 0), (int) ($offer?->quoted_amount_minor ?? 0));
        $nearCeilingPercent = (int) ($s['anomaly_near_ceiling_percent'] ?? 90);
        $limit = $user->role?->slug === 'freelancer' ? $this->freelancerProposalLimitMinor($user) : $this->clientPostingLimitMinor($user);

        if ($ageDays < (int) ($s['anomaly_new_account_days'] ?? 7) && $limit > 0 && $value >= (int) floor($limit * ($nearCeilingPercent / 100))) {
            $this->createAnomalyFlag($user, 'new_account_near_tier_ceiling', compact('ageDays', 'value', 'limit'), $quest, $offer);
        }

        $window = now()->subHours((int) ($s['anomaly_verification_window_hours'] ?? 24));
        $recentVerifications = $user->userVerifications()
            ->whereIn('status', ['approved', 'verified'])
            ->where('reviewed_at', '>=', $window)
            ->count();
        if ($recentVerifications >= 2 && $value >= (int) ($s['anomaly_high_value_minor'] ?? 10_000_000)) {
            $this->createAnomalyFlag($user, 'rapid_verification_then_high_value_action', compact('recentVerifications', 'value'), $quest, $offer);
        }

        if ($offer !== null) {
            $burstCount = (int) ($s['anomaly_proposal_burst_count'] ?? 5);
            $since = now()->subMinutes((int) ($s['anomaly_proposal_burst_minutes'] ?? 60));
            $recentHighValueOffers = QuestOffer::query()
                ->where('freelancer_id', $user->id)
                ->where('created_at', '>=', $since)
                ->where('quoted_amount_minor', '>=', (int) ($s['anomaly_high_value_minor'] ?? 10_000_000))
                ->count();
            if ($recentHighValueOffers >= $burstCount) {
                $this->createAnomalyFlag($user, 'proposal_burst_on_high_value_quests', compact('recentHighValueOffers', 'burstCount'), $quest, $offer);
            }
        }
    }

    public function overrideLevel(User $target, User $actor, int $level, string $reason): void
    {
        $level = max(self::LEVEL_MIN, min($this->maxLevelFor($target), $level));
        $old = $target->only([
            'verification_level_override',
            'verification_level_override_reason',
            'current_verification_level',
            'kyc_tier',
            'verification_tier',
        ]);
        $target->forceFill([
            'verification_level_override' => $level,
            'verification_level_override_reason' => $reason,
            'verification_level_overridden_by' => $actor->id,
            'verification_level_overridden_at' => now(),
            'current_verification_level' => $level,
            'kyc_tier' => $level,
            'verification_tier' => $level,
            'kyc_status' => $level > 0 ? 'verified' : 'unverified',
            'kyc_verified_at' => $level > 0 ? ($target->kyc_verified_at ?? now()) : null,
        ])->save();

        $this->audit($actor, $target, 'verification_level.overridden', $old, $target->only([
            'verification_level_override',
            'verification_level_override_reason',
            'current_verification_level',
            'kyc_tier',
            'verification_tier',
        ]), $reason);
    }

    public function resetUserLimit(User $target, User $actor, ?int $clientLimitMinor, ?int $freelancerLimitMinor, string $reason): void
    {
        $old = $target->only(['custom_client_post_limit_minor', 'custom_freelancer_proposal_limit_minor']);
        $target->forceFill([
            'custom_client_post_limit_minor' => $clientLimitMinor,
            'custom_freelancer_proposal_limit_minor' => $freelancerLimitMinor,
        ])->save();

        $this->audit($actor, $target, 'verification_limits.user_override', $old, $target->only(['custom_client_post_limit_minor', 'custom_freelancer_proposal_limit_minor']), $reason);
    }

    public function audit(?User $actor, ?User $affectedUser, string $action, mixed $oldValue = null, mixed $newValue = null, ?string $reason = null, ?object $subject = null): void
    {
        if (! Schema::hasTable('verification_engine_audit_logs')) {
            return;
        }

        VerificationEngineAuditLog::query()->create([
            'actor_id' => $actor?->id,
            'affected_user_id' => $affectedUser?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id ?? null,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'user_agent' => request() ? Str::limit((string) request()->userAgent(), 2000, '') : null,
        ]);
    }

    private function requirementsMet(array $requirements, array $completed, User $user, bool $includeAccountAge = true): bool
    {
        foreach ($requirements as $requirement) {
            if (is_array($requirement) && isset($requirement['account_age_days'])) {
                if (! $includeAccountAge) {
                    continue;
                }

                if ($user->created_at?->gt(now()->subDays((int) $requirement['account_age_days']))) {
                    return false;
                }

                continue;
            }

            if (is_string($requirement) && ! in_array($requirement, $completed, true)) {
                return false;
            }

            if (is_array($requirement) && isset($requirement['any_of']) && count(array_intersect($requirement['any_of'], $completed)) === 0) {
                return false;
            }
        }

        return true;
    }

    private function limitMessage(User $user, int $limit, string $action): string
    {
        if ($user->verification_restricted_at !== null) {
            return __('Your account is temporarily restricted from this action while an admin reviews verification risk flags.');
        }

        $badgeLevel = 'L'.$this->effectiveLevel($user);
        $limitLevel = 'L'.$this->limitLevel($user);
        $amount = $this->formatMoneyMinor($limit);

        if ($limit <= 0) {
            $missing = implode(', ', $this->missingForNextLevel($user));

            return __("Your current verification level ({$badgeLevel}) cannot {$action}. Complete: {$missing}.");
        }

        $missing = implode(', ', $this->missingForLimitIncrease($user));

        if ($this->limitLevel($user) < $this->earnedLevel($user)) {
            $wait = $this->levelAdvanceAgeWaitContext($user);
            if ($wait !== null) {
                return __("Your verification checks for :level are complete, but your account needs :days more days on HustleSafe before you advance.", [
                    'level' => $wait['target_level_label'],
                    'days' => (int) $wait['days_remaining'],
                ]);
            }
        }

        return __("Your current verification level ({$badgeLevel}) allows up to {$amount}. Complete: {$missing} to unlock a higher limit.");
    }

    /**
     * @return list<string>
     */
    public function missingForNextLevelPublic(User $user): array
    {
        return $this->missingForNextLevel($user, $this->resolvedVerificationLevel($user));
    }

    /**
     * @return list<string>
     */
    public function missingForLimitIncrease(User $user): array
    {
        if ($user->verification_level_override !== null) {
            return [];
        }

        $wait = $this->levelAdvanceAgeWaitContext($user);
        if ($wait !== null && (int) ($wait['days_remaining'] ?? 0) > 0) {
            return [__(':days more days on HustleSafe to reach :level', [
                'days' => (int) $wait['days_remaining'],
                'level' => $wait['target_level_label'],
            ])];
        }

        return $this->missingForNextLevel($user, $this->resolvedVerificationLevel($user));
    }

    /**
     * Human-readable labels for requirements still needed to reach the next client level.
     *
     * @return list<string>
     */
    public function missingRequirementLabelsForNextLevel(User $user): array
    {
        if ($this->isFreelancer($user)) {
            return $this->missingForNextLevelPublic($user);
        }

        $current = $this->resolvedVerificationLevel($user);
        $next = min($this->maxLevelFor($user), $current + 1);
        if ($next <= $current) {
            return $this->missingForLimitIncrease($user);
        }

        if (! $user->hasVerifiedEmail()) {
            return [$this->verificationTypeLabel('email')];
        }

        $completed = $this->completedVerificationTypes($user);
        $requirements = Arr::get($this->clientLevelRequirements(), "{$next}.requirements", []);
        $labels = [];

        foreach ($requirements as $requirement) {
            if (is_array($requirement) && isset($requirement['account_age_days'])) {
                continue;
            }

            if (is_string($requirement) && ! in_array($requirement, $completed, true)) {
                $labels[] = $this->verificationTypeLabel($requirement);
            }
            if (is_array($requirement) && isset($requirement['any_of']) && count(array_intersect($requirement['any_of'], $completed)) === 0) {
                $labels[] = collect($requirement['any_of'])
                    ->map(fn (string $item) => $this->verificationTypeLabel($item))
                    ->implode(' or ');
            }
        }

        $labels = array_values(array_unique(array_filter($labels)));

        return $labels !== [] ? $labels : $this->missingForLimitIncrease($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function clientPostingLimitContextForQuestCreate(User $user): array
    {
        $trust = $this->trustSummaryFor($user, false);
        $platformMaxMinor = $this->platformMaxQuestBudgetMinor();
        $next = $trust['next_level'];
        $stageContent = $next !== null ? $this->stageContentFor($user, $next) : [];
        $missing = $this->missingRequirementLabelsForNextLevel($user);
        $restricted = $user->verification_restricted_at !== null;

        return [
            'current_level' => (int) $trust['current_level'],
            'current_label' => $trust['current_label'],
            'limit_minor' => (int) $trust['limit_minor'],
            'limit_formatted' => $trust['limit_formatted'],
            'limit_capped' => (bool) ($trust['limit_capped'] ?? false),
            'limit_capped_by_age' => (bool) ($trust['limit_capped_by_age'] ?? false),
            'limit_level' => (int) ($trust['limit_level'] ?? $this->limitLevel($user)),
            'limit_level_label' => $trust['limit_level_label'] ?? $this->levelLabel($this->limitLevel($user), $user),
            'earned_limit_formatted' => $trust['earned_limit_formatted'] ?? null,
            'platform_max_minor' => $platformMaxMinor,
            'platform_max_formatted' => $this->formatMoneyMinor($platformMaxMinor),
            'min_quest_budget_minor' => $this->minQuestBudgetMinor(),
            'min_quest_budget_formatted' => $this->formatMoneyMinor($this->minQuestBudgetMinor()),
            'next_level' => $next,
            'next_level_label' => $trust['next_level_label'],
            'next_level_limit_formatted' => $trust['next_level_limit_formatted'],
            'next_unlock_title' => $stageContent['title'] ?? null,
            'next_unlock_hint' => $stageContent['info_bar'] ?? null,
            'missing_requirements' => $missing,
            'restricted' => $restricted,
            'can_post' => ! $restricted && (int) $trust['limit_minor'] > 0,
            'at_max_level' => (bool) ($trust['at_max_level'] ?? false),
            'max_level' => (int) ($trust['max_level'] ?? $this->maxLevelFor($user)),
            'verifications_url' => route('verifications.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function freelancerProposalLimitContext(User $user, ?int $questBudgetMinor = null): array
    {
        $trust = $this->trustSummaryFor($user, true);
        $earned = $this->earnedLevel($user);
        $platformMax = $this->platformMaxProposalValueMinor();

        return [
            'current_level' => (int) $trust['current_level'],
            'current_label' => $trust['current_label'],
            'proposal_limit_minor' => (int) $trust['limit_minor'],
            'proposal_limit_formatted' => $trust['limit_formatted'],
            'earned_proposal_limit_minor' => $this->limitAtLevel($user, $earned),
            'earned_proposal_limit_formatted' => $this->formatMoneyMinor($this->limitAtLevel($user, $earned)),
            'limit_capped' => (bool) ($trust['limit_capped'] ?? false),
            'limit_capped_by_age' => (bool) ($trust['limit_capped_by_age'] ?? false),
            'limit_level' => (int) ($trust['limit_level'] ?? $this->limitLevel($user)),
            'limit_level_label' => $trust['limit_level_label'] ?? $this->levelLabel($this->limitLevel($user), $user),
            'platform_max_minor' => $platformMax,
            'platform_max_formatted' => $this->formatMoneyMinor($platformMax),
            'platform_max_ngn' => $this->platformMaxProposalValueNgn(),
            'next_level' => $trust['next_level'],
            'next_level_label' => $trust['next_level_label'],
            'next_level_limit_formatted' => $trust['next_level_limit_formatted'],
            'max_level' => (int) ($trust['max_level'] ?? $this->maxLevelFor($user)),
            'at_max_level' => (bool) ($trust['at_max_level'] ?? false),
            'missing_for_next_level' => $this->missingForNextLevelPublic($user),
            'quest_budget_minor' => $questBudgetMinor,
            'quest_budget_formatted' => $questBudgetMinor !== null ? $this->formatMoneyMinor($questBudgetMinor) : null,
            'can_propose_on_quest' => $questBudgetMinor === null || $this->freelancerCanProposeForBudget($user, $questBudgetMinor),
            'verifications_url' => route('verifications.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function freelancerVerificationAccessContext(User $user): array
    {
        $context = $this->freelancerProposalLimitContext($user);

        return array_merge($context, [
            'earned_level' => $this->earnedLevel($user),
            'effective_level' => $this->effectiveLevel($user),
            'limit_level' => $this->limitLevel($user),
            'can_submit_for_budget' => null,
        ]);
    }

    private function verificationTypeLabel(string $key): string
    {
        return (string) Arr::get($this->types(), "{$key}.label", Str::headline(str_replace('_', ' ', $key)));
    }

    public function levelLabel(int $level, ?User $user = null): string
    {
        $levels = $user ? $this->levelRequirementsFor($user) : $this->clientLevelRequirements();

        return (string) (Arr::get($levels, "{$level}.label") ?? Arr::get($levels, (string) $level.'.label', "L{$level}"));
    }

    /**
     * @param  array<int|string, mixed>  $map
     * @return list<int>
     */
    private function sortedLevelKeys(array $map): array
    {
        return collect(array_keys($map))
            ->map(fn ($key) => (int) $key)
            ->filter(fn (int $level) => $level >= self::LEVEL_MIN)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $defaults
     * @return array<int, array<string, mixed>>
     */
    private function mergeLevelRequirements(array $defaults, mixed $stored): array
    {
        if (! is_array($stored)) {
            return $this->normalizeLevelRequirementMap($defaults);
        }

        $merged = $this->normalizeLevelRequirementMap($defaults);

        foreach ($stored as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $level = (int) $key;
            $merged[$level] = array_merge($merged[$level] ?? ['label' => "L{$level}", 'requirements' => []], $value);
        }

        ksort($merged, SORT_NUMERIC);

        return $merged;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $map
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLevelRequirementMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $normalized[(int) $key] = $value;
        }

        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param  array<int|string, int>  $defaults
     * @param  array<int|string, int>  $stored
     * @return array<int, int>
     */
    private function mergeLimitLevelMap(array $defaults, array $stored): array
    {
        $keys = array_unique(array_merge(array_keys($defaults), array_keys($stored)));
        $merged = [];

        foreach ($keys as $key) {
            $level = (int) $key;

            if (array_key_exists($key, $stored) || array_key_exists((string) $key, $stored)) {
                $merged[$level] = (int) ($stored[$key] ?? $stored[(string) $key]);
            } else {
                $merged[$level] = (int) ($defaults[$key] ?? $defaults[(string) $key] ?? 0);
            }
        }

        ksort($merged, SORT_NUMERIC);

        return $merged;
    }

    /**
     * @return array{current_level: int, current_label: string, next_level: ?int, next_level_label: ?string, limit_minor: int, limit_label: string, limit_formatted: string, limit_description: string, next_level_limit_minor: ?int, next_level_limit_formatted: ?string, has_override: bool}
     */
    public function trustSummaryFor(User $user, bool $isFreelancer): array
    {
        $maxLevel = $this->maxLevelForRole($isFreelancer);
        $documentLevel = $this->earnedLevel($user);
        $current = $this->resolvedVerificationLevel($user);
        $limitLevel = $this->limitLevel($user);
        $next = $current < $maxLevel ? $current + 1 : null;
        $limitMinor = $isFreelancer
            ? $this->freelancerProposalLimitMinor($user)
            : $this->clientPostingLimitMinor($user);
        $tierLimitMinor = $this->limitAtLevel($user, $limitLevel);
        $nextLimitMinor = $next !== null ? $this->limitAtLevel($user, $next) : null;
        $hasCustomCap = $isFreelancer
            ? $user->custom_freelancer_proposal_limit_minor !== null
            : $user->custom_client_post_limit_minor !== null;
        $waitingForAge = $user->verification_level_override === null && $documentLevel > $current;
        $limitCappedByCustom = $hasCustomCap && $limitMinor < $tierLimitMinor;

        return [
            'earned_level' => $documentLevel,
            'effective_level' => $current,
            'limit_level' => $limitLevel,
            'current_level' => $current,
            'current_label' => $this->levelLabel($current, $user),
            'effective_label' => $this->levelLabel($current, $user),
            'limit_level_label' => $this->levelLabel($limitLevel, $user),
            'next_level' => $next,
            'next_level_label' => $next !== null ? $this->levelLabel($next, $user) : null,
            'waiting_for_account_age' => $waitingForAge,
            'limit_minor' => $limitMinor,
            'limit_label' => $isFreelancer ? __('Proposal limit') : __('Quest posting limit'),
            'limit_formatted' => $this->formatMoneyMinor($limitMinor),
            'limit_description' => $isFreelancer
                ? __('Maximum quest value you can propose on at your current verification level (from platform limit settings).')
                : __('Maximum budget for a quest you can post at your current verification level (from platform limit settings).'),
            'earned_limit_minor' => $tierLimitMinor,
            'earned_limit_formatted' => $this->formatMoneyMinor($tierLimitMinor),
            'limit_applies_level' => $limitLevel,
            'limit_capped' => $waitingForAge || $limitCappedByCustom,
            'limit_capped_by_age' => $waitingForAge,
            'enforced_limit_minor' => $limitMinor,
            'enforced_limit_formatted' => $this->formatMoneyMinor($limitMinor),
            'next_level_limit_minor' => $nextLimitMinor,
            'next_level_limit_formatted' => $nextLimitMinor !== null ? $this->formatMoneyMinor($nextLimitMinor) : null,
            'has_override' => $user->verification_level_override !== null,
            'max_level' => $maxLevel,
            'at_max_level' => $current >= $maxLevel,
            'at_max_limit_level' => $limitLevel >= $maxLevel,
        ];
    }

    public function accountAgeDaysRemaining(User $user, ?int $requiredDays = null): int
    {
        if ($requiredDays === null) {
            $requiredDays = $this->accountAgeRequirementDaysForNextLevel($user);
            if ($requiredDays === null || $requiredDays <= 0) {
                return 0;
            }
        }
        $ageDays = (int) ($user->created_at?->diffInDays(now()) ?? 0);

        return max(0, $requiredDays - $ageDays);
    }

    public function accountAgeRequirementDays(User $user): int
    {
        return $this->accountAgeRequirementDaysForNextLevel($user)
            ?? $this->accountAgeRequirementDaysForLevel($user, $this->maxLevelFor($user))
            ?? 0;
    }

    /**
     * @param  list<mixed>  $requirements
     */
    public function accountAgeDaysFromRequirements(array $requirements): ?int
    {
        foreach ($requirements as $requirement) {
            if (is_array($requirement) && isset($requirement['account_age_days'])) {
                return max(0, (int) $requirement['account_age_days']);
            }
        }

        return null;
    }

    public function accountAgeRequirementDaysForLevel(User $user, int $level): ?int
    {
        $requirements = Arr::get($this->levelRequirementsFor($user), "{$level}.requirements", []);

        return is_array($requirements) ? $this->accountAgeDaysFromRequirements($requirements) : null;
    }

    public function accountAgeRequirementDaysForNextLevel(User $user, ?int $fromLevel = null): ?int
    {
        $current = $fromLevel ?? $this->resolvedVerificationLevel($user);
        $next = $current + 1;

        if ($next > $this->maxLevelFor($user)) {
            return null;
        }

        return $this->accountAgeRequirementDaysForLevel($user, $next);
    }

    /**
     * Next level has all document checks approved but account age is still outstanding.
     *
     * @return array{
     *     target_level: int,
     *     target_level_label: string,
     *     required_days: int,
     *     days_remaining: int,
     *     current_level: int,
     *     completed_checks: list<string>
     * }|null
     */
    public function levelAdvanceAgeWaitContext(User $user): ?array
    {
        if ($user->verification_level_override !== null) {
            return null;
        }

        $current = $this->resolvedVerificationLevel($user);
        $next = $current + 1;
        if ($next > $this->maxLevelFor($user)) {
            return null;
        }

        $completed = $this->completedVerificationTypes($user);
        $requirements = Arr::get($this->levelRequirementsFor($user), "{$next}.requirements", []);
        if (! is_array($requirements) || $requirements === []) {
            return null;
        }

        if (! $this->requirementsMet($requirements, $completed, $user, includeAccountAge: false)) {
            return null;
        }

        if ($this->requirementsMet($requirements, $completed, $user, includeAccountAge: true)) {
            return null;
        }

        $requiredDays = $this->accountAgeDaysFromRequirements($requirements);
        if ($requiredDays === null || $requiredDays <= 0) {
            return null;
        }

        $daysRemaining = $this->accountAgeDaysRemaining($user, $requiredDays);
        if ($daysRemaining <= 0) {
            return null;
        }

        return [
            'target_level' => $next,
            'target_level_label' => $this->levelLabel($next, $user),
            'required_days' => $requiredDays,
            'days_remaining' => $daysRemaining,
            'current_level' => $current,
            'completed_checks' => $this->completedDocumentLabelsForLevel($user, $next, $completed),
        ];
    }

    /**
     * @param  list<string>  $completed
     * @return list<string>
     */
    public function completedDocumentLabelsForLevel(User $user, int $level, array $completed): array
    {
        $requirements = Arr::get($this->levelRequirementsFor($user), "{$level}.requirements", []);
        if (! is_array($requirements)) {
            return [];
        }

        $labels = [];
        foreach ($requirements as $requirement) {
            if (is_array($requirement) && isset($requirement['account_age_days'])) {
                continue;
            }

            if (is_string($requirement) && in_array($requirement, $completed, true)) {
                $labels[] = $this->verificationTypeLabel($requirement).' '.__('verified');
            }

            if (is_array($requirement) && isset($requirement['any_of'])) {
                $matched = array_values(array_intersect($requirement['any_of'], $completed));
                if ($matched !== []) {
                    $labels[] = collect($matched)
                        ->map(fn (string $item) => $this->verificationTypeLabel($item))
                        ->implode(' / ').' '.__('verified');
                }
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @deprecated Use levelAdvanceAgeWaitContext()
     *
     * @return array{target_level: int, target_level_label: string, required_days: int, days_remaining: int, earned_level: int, limit_level: int}|null
     */
    public function accountAgeWaitContext(User $user): ?array
    {
        $wait = $this->levelAdvanceAgeWaitContext($user);
        if ($wait === null) {
            return null;
        }

        return array_merge($wait, [
            'earned_level' => $this->earnedLevel($user),
            'limit_level' => $this->resolvedVerificationLevel($user),
        ]);
    }

    public function accountAgeDaysRemainingForLevel(User $user, int $level): int
    {
        $requiredDays = $this->accountAgeRequirementDaysForLevel($user, $level);
        if ($requiredDays === null || $requiredDays <= 0) {
            return 0;
        }

        return $this->accountAgeDaysRemaining($user, $requiredDays);
    }

    /**
     * @param  array{title?: string, message?: string, info_bar?: string}  $content
     * @return array{title?: string, message?: string, info_bar?: string}
     */
    public function interpolateAccountAgeStageContent(User $user, int $level, array $content): array
    {
        $requiredDays = $this->accountAgeRequirementDaysForLevel($user, $level);
        if ($requiredDays === null) {
            return $content;
        }

        $replacements = [
            '{account_age_days}' => (string) $requiredDays,
            ':account_age_days' => (string) $requiredDays,
        ];

        foreach ($content as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $content[$key] = str_replace(array_keys($replacements), array_values($replacements), $value);

            // Normalise legacy stage copy that still hardcodes an old day count.
            $content[$key] = preg_replace('/\b\d+\s*(?:-\s*)?days\b/i', "{$requiredDays} days", $content[$key]) ?? $content[$key];
        }

        return $content;
    }

    private function levelKeyForCategory(?UserVerificationCategory $category, string $verificationType): string
    {
        if ($verificationType !== '') {
            return match ($verificationType) {
                'cac', 'business' => 'cac',
                'qualification' => 'professional_certificate',
                default => $verificationType,
            };
        }

        return match ($category) {
            UserVerificationCategory::IdentityAddress => 'identity_address',
            UserVerificationCategory::Cac => 'cac',
            UserVerificationCategory::Tin => 'tin',
            UserVerificationCategory::Business => 'cac',
            UserVerificationCategory::Qualification => 'professional_certificate',
            default => $category?->value ?? '',
        };
    }

    private function missingForNextLevel(User $user, ?int $fromLevel = null): array
    {
        $current = $fromLevel ?? $this->resolvedVerificationLevel($user);
        $maxLevel = $this->maxLevelForRole($this->isFreelancer($user));
        $next = min($maxLevel, $current + 1);
        if ($next <= $current) {
            return [];
        }

        $completed = $this->completedVerificationTypes($user);
        $requirements = Arr::get($this->levelRequirementsFor($user), "{$next}.requirements", []);
        $missing = [];

        foreach ($requirements as $requirement) {
            if (is_array($requirement) && isset($requirement['account_age_days'])) {
                continue;
            }

            if (is_string($requirement) && ! in_array($requirement, $completed, true)) {
                $missing[] = Str::headline($requirement);
            }
            if (is_array($requirement) && isset($requirement['any_of']) && count(array_intersect($requirement['any_of'], $completed)) === 0) {
                $missing[] = collect($requirement['any_of'])->map(fn ($item) => Str::upper($item))->implode(' or ');
            }
        }

        return $missing ?: ['the next verification requirement'];
    }

    private function createAnomalyFlag(User $user, string $type, array $context, ?Quest $quest = null, ?QuestOffer $offer = null): VerificationAnomalyFlag
    {
        return VerificationAnomalyFlag::query()->firstOrCreate([
            'user_id' => $user->id,
            'type' => $type,
            'status' => 'open',
            'quest_id' => $quest?->id,
            'quest_offer_id' => $offer?->id,
        ], [
            'severity' => 'high',
            'context' => $context,
        ]);
    }
}
