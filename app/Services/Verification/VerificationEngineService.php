<?php

namespace App\Services\Verification;

use App\Enums\QuestStatus;
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
    public const LEVEL_MAX = 5;

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
        return $this->setting('verification_level_requirements', config('verification_engine.levels', []));
    }

    public function limits(): array
    {
        return $this->setting('verification_limits', config('verification_engine.limits', []));
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

            $completed[] = (string) ($record->verification_type ?: $record->category?->value ?: $record->category);
        }

        if (in_array('identity', $completed, true) && in_array('address', $completed, true)) {
            $completed[] = 'identity_address';
        }
        if (in_array('business', $completed, true)) {
            $completed[] = 'cac';
        }
        if (in_array('qualification', $completed, true)) {
            $completed[] = 'professional_certificate';
        }

        return array_values(array_unique($completed));
    }

    public function earnedLevel(User $user): int
    {
        $completed = $this->completedVerificationTypes($user);
        $levels = $this->levelRequirements();
        $earned = 0;

        foreach (range(self::LEVEL_MIN, self::LEVEL_MAX) as $level) {
            $requirements = Arr::get($levels, "{$level}.requirements", []);
            if ($this->requirementsMet($requirements, $completed, $user)) {
                $earned = $level;
            }
        }

        return $earned;
    }

    public function storedLevel(User $user): int
    {
        $stored = (int) ($user->current_verification_level ?? $user->kyc_tier ?? $user->verification_tier ?? 0);
        $override = $user->verification_level_override;

        return $override === null ? $stored : (int) $override;
    }

    public function effectiveLevel(User $user): int
    {
        $level = $this->storedLevel($user);
        $cooldownDays = (int) ($this->safeguards()['new_account_cooldown_days'] ?? 30);

        if ($cooldownDays > 0 && $user->created_at?->gt(now()->subDays($cooldownDays)) && $level > 0) {
            return $level - 1;
        }

        return $level;
    }

    public function cooldown(User $user): array
    {
        $days = (int) ($this->safeguards()['new_account_cooldown_days'] ?? 30);
        $expiresAt = $days > 0 ? $user->created_at?->copy()->addDays($days) : null;
        $active = $expiresAt !== null && now()->lt($expiresAt) && $this->storedLevel($user) > $this->effectiveLevel($user);

        return [
            'active' => $active,
            'days' => $days,
            'expires_at' => $expiresAt?->toIso8601String(),
            'display_level' => $this->effectiveLevel($user),
            'earned_level' => $this->storedLevel($user),
        ];
    }

    public function recalculate(User $user, ?User $actor = null, ?string $reason = null): int
    {
        $old = [
            'current_verification_level' => (int) ($user->current_verification_level ?? $user->kyc_tier ?? $user->verification_tier ?? 0),
            'kyc_tier' => (int) ($user->kyc_tier ?? 0),
            'verification_tier' => (int) ($user->verification_tier ?? 0),
        ];
        $earned = $this->earnedLevel($user);

        $user->forceFill([
            'current_verification_level' => $earned,
            'kyc_tier' => $earned,
            'verification_tier' => $earned,
            'kyc_status' => $earned > 0 ? 'verified' : 'unverified',
            'kyc_verified_at' => $earned > 0 ? ($user->kyc_verified_at ?? now()) : null,
        ])->saveQuietly();

        if ($old['current_verification_level'] !== $earned) {
            $this->audit($actor, $user, 'verification_level.recalculated', $old, [
                'current_verification_level' => $earned,
            ], $reason);
        }

        return $earned;
    }

    public function clientPostingLimitMinor(User $user): int
    {
        if ($user->verification_restricted_at !== null) {
            return 0;
        }
        if ($user->custom_client_post_limit_minor !== null) {
            return (int) $user->custom_client_post_limit_minor;
        }

        return (int) Arr::get($this->limits(), 'client_posting_minor.'.$this->effectiveLevel($user), 0);
    }

    public function freelancerProposalLimitMinor(User $user): int
    {
        if ($user->verification_restricted_at !== null) {
            return 0;
        }
        if ($user->custom_freelancer_proposal_limit_minor !== null) {
            return (int) $user->custom_freelancer_proposal_limit_minor;
        }

        return (int) Arr::get($this->limits(), 'freelancer_proposal_minor.'.$this->effectiveLevel($user), 0);
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
        $old = $target->only(['verification_level_override', 'verification_level_override_reason']);
        $target->forceFill([
            'verification_level_override' => max(self::LEVEL_MIN, min(self::LEVEL_MAX, $level)),
            'verification_level_override_reason' => $reason,
            'verification_level_overridden_by' => $actor->id,
            'verification_level_overridden_at' => now(),
        ])->save();

        $this->audit($actor, $target, 'verification_level.overridden', $old, $target->only(['verification_level_override', 'verification_level_override_reason']), $reason);
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

    private function requirementsMet(array $requirements, array $completed, User $user): bool
    {
        foreach ($requirements as $requirement) {
            if (is_string($requirement) && ! in_array($requirement, $completed, true)) {
                return false;
            }
            if (is_array($requirement) && isset($requirement['any_of']) && count(array_intersect($requirement['any_of'], $completed)) === 0) {
                return false;
            }
            if (is_array($requirement) && isset($requirement['account_age_days']) && $user->created_at?->gt(now()->subDays((int) $requirement['account_age_days']))) {
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

        $level = 'L'.$this->effectiveLevel($user);
        $amount = '₦'.number_format($limit / 100, 0);
        $missing = implode(', ', $this->missingForNextLevel($user));

        return $limit <= 0
            ? __("Your current verification level ({$level}) cannot {$action}. Complete: {$missing}.")
            : __("Your current verification level ({$level}) allows up to {$amount}. Complete: {$missing} to unlock a higher limit.");
    }

    private function missingForNextLevel(User $user): array
    {
        $next = min(self::LEVEL_MAX, $this->storedLevel($user) + 1);
        $completed = $this->completedVerificationTypes($user);
        $requirements = Arr::get($this->levelRequirements(), "{$next}.requirements", []);
        $missing = [];

        foreach ($requirements as $requirement) {
            if (is_string($requirement) && ! in_array($requirement, $completed, true)) {
                $missing[] = Str::headline($requirement);
            }
            if (is_array($requirement) && isset($requirement['any_of']) && count(array_intersect($requirement['any_of'], $completed)) === 0) {
                $missing[] = collect($requirement['any_of'])->map(fn ($item) => Str::upper($item))->implode(' or ');
            }
            if (is_array($requirement) && isset($requirement['account_age_days'])) {
                $missing[] = ((int) $requirement['account_age_days']).' days account age';
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
