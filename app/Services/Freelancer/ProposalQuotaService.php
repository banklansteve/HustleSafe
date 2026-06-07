<?php

namespace App\Services\Freelancer;

use App\Enums\FreelancerSubscriptionTier;
use App\Models\ProposalQuotaAuditLog;
use App\Models\ProposalQuotaUsage;
use App\Models\Quest;
use App\Models\User;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Validation\ValidationException;

final class ProposalQuotaService
{
    /**
     * Monthly proposal submission quota only (per verification tier).
     *
     * Orthogonal to VerificationEngineService job value limits — Pro removes
     * the monthly count cap but never bypasses tier, age, or budget gates.
     */
    public function __construct(
        private readonly FreelancerProSubscriptionService $subscriptions,
        private readonly VerificationEngineService $verificationEngine,
    ) {}

    public function currentMonth(): string
    {
        return now()->format('Y-m');
    }

    public function isPro(User $user): bool
    {
        return $this->subscriptions->isPro($user);
    }

    public function monthlyLimitFor(User $user): int
    {
        return $this->verificationEngine->freelancerMonthlyProposalLimit($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function usagePayload(User $user): array
    {
        $isPro = $this->isPro($user);
        $month = $this->currentMonth();
        $usage = $this->usageRecord($user, $month);
        $limit = $this->monthlyLimitFor($user);
        $used = (int) $usage->proposals_count;
        $level = $this->verificationEngine->limitLevel($user);

        return [
            'month' => $month,
            'is_pro' => $isPro,
            'limit' => $isPro ? null : $limit,
            'used' => $used,
            'remaining' => $isPro ? null : max(0, $limit - $used),
            'percent_used' => $isPro ? 0 : ($limit > 0 ? min(100, round(($used / $limit) * 100)) : 0),
            'verification_level' => $level,
            'limit_source' => $isPro ? 'pro_unlimited' : 'verification_tier',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function assertCanSubmit(User $user, ?Quest $quest = null): void
    {
        $isPro = $this->isPro($user);
        $month = $this->currentMonth();
        $usage = $this->usageRecord($user, $month);
        $limit = $this->monthlyLimitFor($user);
        $used = (int) $usage->proposals_count;
        $tier = $isPro ? FreelancerSubscriptionTier::Pro->value : FreelancerSubscriptionTier::Free->value;

        if ($isPro) {
            $this->audit($user, $month, $tier, $used, null, 'allowed', $quest?->id);

            return;
        }

        if ($limit <= 0) {
            $this->audit($user, $month, $tier, $used, $limit, 'blocked', $quest?->id);

            throw ValidationException::withMessages([
                'proposal' => [__('Your verification level does not allow proposal submissions yet.')],
                'quota_exceeded' => [true],
            ]);
        }

        if ($used >= $limit) {
            $this->audit($user, $month, $tier, $used, $limit, 'blocked', $quest?->id);
            $levelLabel = $this->verificationEngine->levelLabel(
                $this->verificationEngine->limitLevel($user),
                $user,
            );

            throw ValidationException::withMessages([
                'proposal' => [__(
                    'You\'ve used your :limit monthly proposals at :level. Upgrade to Pro for unlimited submissions (job value limits still apply).',
                    ['limit' => $limit, 'level' => $levelLabel],
                )],
                'quota_exceeded' => [true],
            ]);
        }

        $this->audit($user, $month, $tier, $used, $limit, 'allowed', $quest?->id);
    }

    public function recordSubmission(User $user): void
    {
        if ($this->isPro($user)) {
            return;
        }

        $month = $this->currentMonth();
        $usage = $this->usageRecord($user, $month);
        $usage->increment('proposals_count');
        $usage->update(['plan_tier' => FreelancerSubscriptionTier::Free->value]);
    }

    private function usageRecord(User $user, string $month): ProposalQuotaUsage
    {
        return ProposalQuotaUsage::query()->firstOrCreate(
            ['freelancer_id' => $user->id, 'month' => $month],
            [
                'proposals_count' => 0,
                'plan_tier' => $this->isPro($user)
                    ? FreelancerSubscriptionTier::Pro->value
                    : FreelancerSubscriptionTier::Free->value,
            ],
        );
    }

    private function audit(
        User $user,
        string $month,
        string $tier,
        int $used,
        ?int $limit,
        string $result,
        ?int $questId,
    ): void {
        ProposalQuotaAuditLog::query()->create([
            'freelancer_id' => $user->id,
            'month' => $month,
            'plan_tier' => $tier,
            'proposals_used' => $used,
            'quota_limit' => $limit,
            'result' => $result,
            'quest_id' => $questId,
            'occurred_at' => now(),
        ]);
    }
}
