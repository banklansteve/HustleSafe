<?php

namespace App\Services\TrustRisk;

use App\Models\LoginEvent;
use App\Models\ModerationCase;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\User;
use App\Models\UserReferral;
use App\Models\UserVerification;
use App\Models\WalletBankAccount;
use App\Services\Payments\PaymentMonitoringAnomalyEngine;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserRiskScoreCalculator
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $cachedPaymentAnomalies = null;

    public function __construct(
        private readonly TrustRiskSettingsService $settings,
        private readonly PaymentMonitoringAnomalyEngine $paymentEngine,
        private readonly VerificationEngineService $verificationEngine,
    ) {}

    /**
     * @return array{composite: int, breakdown: array<string, array{score: float, weight: float, contribution: float, label: string}>, signals: array<string, mixed>}
     */
    public function calculate(User $user): array
    {
        $weights = $this->settings->weights();

        $categories = [
            'kyc' => $this->scoreKyc($user),
            'account_activity' => $this->scoreAccountActivity($user),
            'disputes' => $this->scoreDisputes($user),
            'flagged_conversations' => $this->scoreFlaggedConversations($user),
            'review_authenticity' => $this->scoreReviewAuthenticity($user),
            'payment_behaviour' => $this->paymentRiskForUser($user->id),
            'device_ip' => $this->scoreDeviceIp($user),
            'velocity' => $this->scoreVelocity($user),
        ];

        $labels = [
            'kyc' => 'KYC completeness',
            'account_activity' => 'Account age vs activity',
            'disputes' => 'Dispute history',
            'flagged_conversations' => 'Flagged conversations',
            'review_authenticity' => 'Review authenticity',
            'payment_behaviour' => 'Payment behaviour',
            'device_ip' => 'Device & IP',
            'velocity' => 'Activity velocity',
        ];

        $composite = 0.0;
        $breakdown = [];

        foreach ($categories as $key => $score) {
            $weight = (float) ($weights[$key] ?? 0);
            $contribution = round($score * $weight, 2);
            $composite += $contribution;
            $breakdown[$key] = [
                'score' => round($score, 1),
                'weight' => $weight,
                'contribution' => $contribution,
                'label' => $labels[$key] ?? $key,
            ];
        }

        return [
            'composite' => (int) min(100, max(0, round($composite))),
            'breakdown' => $breakdown,
            'signals' => $this->buildSignals($user, $categories),
        ];
    }

    private function paymentRiskForUser(int $userId): float
    {
        if ($this->cachedPaymentAnomalies === null) {
            $this->cachedPaymentAnomalies = $this->paymentEngine->detectAll();
        }

        $weights = ['high' => 35, 'medium' => 20, 'low' => 8];

        return (float) min(100, $this->cachedPaymentAnomalies
            ->filter(function (array $row) use ($userId): bool {
                $clientId = (int) ($row['client']['id'] ?? 0);
                $freelancerId = (int) ($row['freelancer']['id'] ?? 0);
                $metaFreelancer = (int) ($row['metadata']['freelancer_id'] ?? 0);

                return $clientId === $userId || $freelancerId === $userId || $metaFreelancer === $userId;
            })
            ->sum(fn (array $row) => $weights[$row['severity']] ?? 10));
    }

    private function scoreKyc(User $user): float
    {
        if (! Schema::hasTable('user_verifications')) {
            return 50;
        }

        $verifications = UserVerification::query()
            ->where('user_id', $user->id)
            ->get();

        if ($verifications->isEmpty()) {
            return 85;
        }

        $approved = $verifications->where('status', 'approved')->count();
        $pending = $verifications->whereIn('status', ['pending', 'in_review', 'flagged'])->count();
        $rejected = $verifications->where('status', 'rejected')->count();
        $total = max(1, $verifications->count());

        $completeness = ($approved / $total) * 100;
        $liveness = (float) $verifications->max('confidence_score') ?: 0;
        if ($liveness <= 1) {
            $liveness *= 100;
        }

        $level = $this->verificationEngine->effectiveLevel($user);
        $maxLevel = VerificationEngineService::LEVEL_MAX;
        $levelPct = ($level / max(1, $maxLevel)) * 100;

        $risk = 100 - (($completeness * 0.35) + ($levelPct * 0.35) + ($liveness * 0.30));
        $risk += $pending * 8;
        $risk += $rejected * 12;

        if ($approved === 0) {
            $risk = max($risk, 75);
        }

        return (float) min(100, max(0, $risk));
    }

    private function scoreAccountActivity(User $user): float
    {
        $created = $user->created_at ?? now();
        $tenureDays = max(1, $created->diffInDays(now()));
        $contracts = $this->contractCount($user);

        $cfg = config('trust_risk.account_activity', []);
        $floor = (int) ($cfg['tenure_floor_days'] ?? 3);
        $threshold = (int) ($cfg['contracts_anomaly_threshold'] ?? 5);

        if ($tenureDays >= $floor && $contracts <= 1) {
            return 5;
        }

        $ratio = $contracts / max(1, $tenureDays);
        $normalized = $ratio / max(0.01, $threshold / $floor);
        $exponential = 100 * (1 - exp(-$normalized * 2));

        return (float) min(100, max(0, $exponential));
    }

    private function scoreDisputes(User $user): float
    {
        if (! Schema::hasTable('quest_disputes')) {
            return 0;
        }

        $against = QuestDispute::query()
            ->where(function ($q) use ($user): void {
                $q->whereHas('quest', fn ($qq) => $qq->where('client_id', $user->id))
                    ->orWhereHas('offer', fn ($qq) => $qq->where('freelancer_id', $user->id));
            })
            ->get();

        $total = $against->count();
        if ($total === 0) {
            return 0;
        }

        $contracts = max(1, $this->contractCount($user));
        $rate = ($total / $contracts) * 100;
        $lost = $against->filter(fn (QuestDispute $d) => (int) $d->ruling_favoured_user_id !== (int) $user->id && $d->resolved_at)->count();

        $score = min(100, $total * 12 + $rate * 1.5 + $lost * 10);

        return (float) $score;
    }

    private function scoreFlaggedConversations(User $user): float
    {
        $count = 0;
        if (Schema::hasTable('conversation_message_flags')) {
            $count = \App\Models\ConversationMessageFlag::query()
                ->where('sender_user_id', $user->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('flagged_at', '>=', now()->subDays(90))
                ->count();
        } elseif (Schema::hasTable('moderation_cases')) {
            $count = ModerationCase::query()
                ->where('subject_user_id', $user->id)
                ->whereIn('status', ['open', 'in_review', 'escalated', 'resolved'])
                ->count();
        }

        if (Schema::hasTable('conversation_user_health_scores')) {
            $health = \App\Models\ConversationUserHealthScore::query()->where('user_id', $user->id)->first();
            if ($health && $health->health_score < app(\App\Services\ConversationMonitoring\ConversationHealthScoreService::class)->healthThreshold()) {
                return (float) min(100, 40 + (100 - $health->health_score) * 0.5);
            }
        }

        return (float) min(100, $count * 18);
    }

    private function scoreReviewAuthenticity(User $user): float
    {
        if (! Schema::hasTable('reviews')) {
            return 0;
        }

        $reviewsReceived = Review::query()
            ->where('reviewee_id', $user->id)
            ->where('status', 'published')
            ->with('quest:id,completed_at')
            ->get();

        if ($reviewsReceived->isEmpty()) {
            return 0;
        }

        $suspicious = 0;
        $userIps = LoginEvent::query()
            ->where('user_id', $user->id)
            ->where('logged_in_at', '>=', now()->subDays(90))
            ->pluck('ip_address')
            ->filter()
            ->unique();

        foreach ($reviewsReceived as $review) {
            $reviewerIps = LoginEvent::query()
                ->where('user_id', $review->reviewer_id)
                ->where('logged_in_at', '>=', now()->subDays(90))
                ->pluck('ip_address');

            if ($userIps->intersect($reviewerIps)->isNotEmpty()) {
                $suspicious++;
                continue;
            }

            $completed = $review->quest?->completed_at;
            if ($completed && $review->created_at && $review->created_at->diffInMinutes($completed) <= 30) {
                $suspicious++;
            }
        }

        $ratio = $suspicious / max(1, $reviewsReceived->count());

        return (float) min(100, $ratio * 100);
    }

    private function scoreDeviceIp(User $user): float
    {
        $lookback = now()->subDays((int) config('trust_risk.device_ip.lookback_days', 30));
        $ips = collect();
        $devices = collect();

        if (Schema::hasTable('login_events')) {
            $logins = LoginEvent::query()
                ->where('user_id', $user->id)
                ->where('logged_in_at', '>=', $lookback)
                ->get(['ip_address', 'user_agent']);

            $ips = $logins->pluck('ip_address')->filter()->unique();
            $devices = $logins->pluck('user_agent')->filter()->unique();
        }

        if (Schema::hasTable('user_referrals')) {
            $refFp = UserReferral::query()
                ->where(fn ($q) => $q->where('referrer_user_id', $user->id)->orWhere('referred_user_id', $user->id))
                ->whereNotNull('device_fingerprint')
                ->pluck('device_fingerprint');
            $devices = $devices->merge($refFp)->unique();
        }

        $deviceCount = $devices->count();
        $ipCount = $ips->count();
        $flagDevices = (int) config('trust_risk.device_ip.distinct_devices_flag', 4);

        $score = 0.0;
        if ($deviceCount > $flagDevices) {
            $score += min(50, ($deviceCount - $flagDevices) * 12);
        }
        if ($ipCount > $flagDevices) {
            $score += min(30, ($ipCount - $flagDevices) * 8);
        }

        $proxyLike = $ips->filter(fn ($ip) => $this->looksLikeAnonymizer((string) $ip))->count();
        if ($proxyLike > 0) {
            $score += min(40, $proxyLike * 15);
        }

        return (float) min(100, $score);
    }

    private function scoreVelocity(User $user): float
    {
        $days = (int) config('trust_risk.velocity.lookback_days', 30);
        $multiplier = (float) config('trust_risk.velocity.spike_multiplier', 2.5);
        $since = now()->subDays($days);
        $today = now()->subDay();

        $metrics = [
            'quests' => Quest::query()->where('client_id', $user->id)->where('created_at', '>=', $since)->count(),
            'proposals' => QuestOffer::query()->where('freelancer_id', $user->id)->where('created_at', '>=', $since)->count(),
            'contracts' => PaymentEscrow::query()
                ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
                ->where('created_at', '>=', $since)
                ->count(),
        ];

        $recentDay = [
            'quests' => Quest::query()->where('client_id', $user->id)->where('created_at', '>=', $today)->count(),
            'proposals' => QuestOffer::query()->where('freelancer_id', $user->id)->where('created_at', '>=', $today)->count(),
            'contracts' => PaymentEscrow::query()
                ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
                ->where('created_at', '>=', $today)
                ->count(),
        ];

        $baselineDaily = array_map(fn ($v) => max(0.1, $v / $days), $metrics);
        $spikeScore = 0.0;

        foreach ($recentDay as $key => $count) {
            if ($count >= $baselineDaily[$key] * $multiplier && $count >= 3) {
                $spikeScore += 25;
            }
        }

        return (float) min(100, $spikeScore);
    }

    private function contractCount(User $user): int
    {
        $offers = 0;
        if (Schema::hasTable('quest_offers')) {
            $offers = QuestOffer::query()
                ->where(fn ($q) => $q->where('freelancer_id', $user->id))
                ->whereNotNull('accepted_at')
                ->count();
        }

        $escrows = 0;
        if (Schema::hasTable('payment_escrows')) {
            $escrows = PaymentEscrow::query()
                ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
                ->whereNotNull('funded_at')
                ->count();
        }

        return max($offers, $escrows);
    }

    private function looksLikeAnonymizer(string $ip): bool
    {
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * @param  array<string, float>  $categories
     * @return array<string, mixed>
     */
    private function buildSignals(User $user, array $categories): array
    {
        return [
            'user_id' => $user->id,
            'categories' => $categories,
            'contract_count' => $this->contractCount($user),
            'bank_accounts' => Schema::hasTable('wallet_bank_accounts')
                ? WalletBankAccount::query()->where('user_id', $user->id)->count()
                : 0,
            'calculated_at' => now()->toIso8601String(),
        ];
    }
}
