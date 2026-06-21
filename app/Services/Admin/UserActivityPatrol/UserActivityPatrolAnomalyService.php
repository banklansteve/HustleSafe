<?php

namespace App\Services\Admin\UserActivityPatrol;

use App\Enums\FreelancerSubscriptionTier;
use App\Enums\QuestDisputeStatus;
use App\Enums\UserActivityAnomalyType;
use App\Enums\UserActivityPatrolStatus;
use App\Enums\UserActivityRiskLevel;
use App\Models\ConversationMessageFlag;
use App\Models\ConversationThreadReview;
use App\Models\FreelancerSubscription;
use App\Models\PaymentEscrow;
use App\Models\PaymentReviewFlag;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\ReviewAuthenticitySignal;
use App\Models\LoginEvent;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestConversationThread;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\User;
use App\Models\UserActivityPatrolFlag;
use App\Models\UserIdentityDocument;
use App\Models\UserVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class UserActivityPatrolAnomalyService
{
    private const EXCLUDED_ROLE_SLUGS = ['admin', 'super_admin'];

    public function __construct(
        private readonly UserActivityPatrolBroadcastService $broadcast,
    ) {}

    public function scanAll(): int
    {
        $created = 0;
        $created += $this->scanDisputeSpikes();
        $created += $this->scanVelocitySpikes();
        $created += $this->scanNewAccountHighValue();
        $created += $this->scanSharedIpAccounts();
        $created += $this->scanConversationFlags();
        $created += $this->scanReviewVelocity();
        $created += $this->scanReciprocalReviews();
        $created += $this->scanSentimentMismatch();
        $created += $this->scanVerificationInconsistency();
        $created += $this->scanCancellationPatterns();
        $created += $this->scanWinRateAnomalies();
        $created += $this->scanPremiumAnomalies();
        $created += $this->scanLowTrustScores();
        $created += $this->scanChargebacks();
        $created += $this->scanRefundRates();
        $created += $this->scanPaymentMethodChanges();
        $created += $this->scanSharedIdentity();
        $created += $this->scanSharedKycDocuments();
        $created += $this->scanEscrowRoundTripping();
        $created += $this->scanLocationAnomalies();
        $created += $this->scanDeviceAnomalies();
        $created += $this->scanAccountInconsistency();
        $created += $this->autoResolveEligible();

        return $created;
    }

    public function scanUser(User $user): int
    {
        if ($this->isExcludedStaffUser($user)) {
            return 0;
        }

        $created = 0;
        $created += $this->flagDisputeSpikeForUser($user) ? 1 : 0;
        $created += $this->flagVelocitySpikeForUser($user) ? 1 : 0;
        $created += $this->flagNewAccountHighValueForUser($user) ? 1 : 0;
        $created += $this->flagSharedIpForUser($user) ? 1 : 0;
        $created += $this->flagConversationForUser($user) ? 1 : 0;
        $created += $this->flagReviewVelocityForUser($user) ? 1 : 0;
        $created += $this->flagVerificationForUser($user) ? 1 : 0;
        $created += $this->flagCancellationForUser($user) ? 1 : 0;
        $created += $this->flagWinRateForUser($user) ? 1 : 0;
        $created += $this->flagPremiumForUser($user) ? 1 : 0;
        $created += $this->flagLowTrustForUser($user) ? 1 : 0;
        $created += $this->flagChargebackForUser($user) ? 1 : 0;
        $created += $this->flagRefundRateForUser($user) ? 1 : 0;
        $created += $this->flagPaymentMethodChangeForUser($user) ? 1 : 0;
        $created += $this->flagSharedIdentityForUser($user) ? 1 : 0;
        $created += $this->flagSharedKycDocumentForUser($user) ? 1 : 0;
        $created += $this->flagEscrowRoundTrippingForUser($user) ? 1 : 0;
        $created += $this->flagLocationAnomalyForUser($user) ? 1 : 0;
        $created += $this->flagDeviceAnomalyForUser($user) ? 1 : 0;
        $created += $this->flagReciprocalForUser($user) ? 1 : 0;
        $created += $this->flagSentimentMismatchForUser($user) ? 1 : 0;

        return $created;
    }

    private function scanDisputeSpikes(): int
    {
        $created = 0;
        $hours = (int) config('user_activity_patrol.dispute_spike_hours', 72);
        $threshold = (int) config('user_activity_patrol.dispute_spike_count', 3);
        $since = now()->subHours($hours);

        $userIds = QuestDispute::query()
            ->where('created_at', '>=', $since)
            ->select('opened_by_user_id')
            ->groupBy('opened_by_user_id')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->pluck('opened_by_user_id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if ($user) {
                $created += $this->flagDisputeSpikeForUser($user) ? 1 : 0;
            }
        }

        $freelancerIds = QuestDispute::query()
            ->where('created_at', '>=', $since)
            ->whereHas('quest', fn ($q) => $q->whereNotNull('freelancer_id'))
            ->with('quest:id,freelancer_id')
            ->get()
            ->groupBy(fn ($d) => $d->quest?->freelancer_id)
            ->filter(fn ($group, $fid) => $fid && $group->count() >= $threshold);

        foreach ($freelancerIds as $freelancerId => $group) {
            $user = User::query()->find($freelancerId);
            if ($user) {
                $created += $this->flagDisputeSpikeForUser($user, 'freelancer') ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagDisputeSpikeForUser(User $user, string $role = 'opener'): bool
    {
        $hours = (int) config('user_activity_patrol.dispute_spike_hours', 72);
        $threshold = (int) config('user_activity_patrol.dispute_spike_count', 3);
        $since = now()->subHours($hours);

        if ($role === 'freelancer') {
            $count = QuestDispute::query()
                ->where('created_at', '>=', $since)
                ->whereHas('quest', fn ($q) => $q->where('freelancer_id', $user->id))
                ->count();
        } else {
            $count = QuestDispute::query()
                ->where('opened_by_user_id', $user->id)
                ->where('created_at', '>=', $since)
                ->count();
        }

        if ($count < $threshold) {
            return false;
        }

        $score = $count >= 3 ? 78 : ($count >= 2 ? 58 : 38);
        $windowLabel = "{$hours}h";

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::DisputeSpike,
            "dispute_spike:{$user->id}:{$role}",
            "{$count} disputes in {$windowLabel} window",
            [
                'dispute_count' => $count,
                'window_hours' => $hours,
                'role' => $role,
            ],
            $score,
        );
    }

    private function scanVelocitySpikes(): int
    {
        $created = 0;
        $hours = (int) config('user_activity_patrol.velocity_spike_hours', 2);
        $since = now()->subHours($hours);

        $counts = QuestOffer::query()
            ->where('created_at', '>=', $since)
            ->select('freelancer_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('freelancer_id')
            ->having('cnt', '>=', 8)
            ->pluck('cnt', 'freelancer_id');

        foreach ($counts as $userId => $count) {
            $user = User::query()->find($userId);
            if ($user) {
                $created += $this->flagVelocitySpikeForUser($user, (int) $count) ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagVelocitySpikeForUser(User $user, ?int $recentCount = null): bool
    {
        $hours = (int) config('user_activity_patrol.velocity_spike_hours', 2);
        $since = now()->subHours($hours);
        $count = $recentCount ?? QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->where('created_at', '>=', $since)
            ->count();

        $baselineDays = 14;
        $baseline = max(1, (int) QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->where('created_at', '>=', now()->subDays($baselineDays))
            ->count() / $baselineDays);

        $multiplier = (int) config('user_activity_patrol.velocity_spike_multiplier', 5);
        $threshold = max(6, $baseline * $multiplier);

        if ($count < $threshold) {
            return false;
        }

        $hasDisputes = QuestDispute::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($q) use ($user): void {
                $q->where('opened_by_user_id', $user->id)
                    ->orWhereHas('quest', fn ($qq) => $qq->where('freelancer_id', $user->id));
            })
            ->exists();

        $score = $hasDisputes ? 62 : 42;

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::VelocitySpike,
            "velocity_spike:{$user->id}",
            "{$count} proposals in {$hours}h (normal ~{$baseline}/day)",
            [
                'proposal_count' => $count,
                'window_hours' => $hours,
                'baseline_daily' => $baseline,
            ],
            $score,
        );
    }

    private function scanNewAccountHighValue(): int
    {
        $created = 0;
        $days = (int) config('user_activity_patrol.new_account_days', 7);
        $minMinor = (int) config('user_activity_patrol.new_account_high_value_minor', 500_000_00);

        User::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->chunk(100, function ($users) use (&$created, $minMinor): void {
                foreach ($users as $user) {
                    $created += $this->flagNewAccountHighValueForUser($user, $minMinor) ? 1 : 0;
                }
            });

        return $created;
    }

    private function flagNewAccountHighValueForUser(User $user, ?int $minMinor = null): bool
    {
        $days = (int) config('user_activity_patrol.new_account_days', 7);
        $minMinor ??= (int) config('user_activity_patrol.new_account_high_value_minor', 500_000_00);

        if ($user->created_at->diffInDays(now()) >= $days) {
            return false;
        }

        $contractValue = (int) Quest::query()
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->whereNotNull('accepted_quest_offer_id')
            ->sum('budget_amount_minor');

        if ($contractValue < $minMinor) {
            return false;
        }

        $score = $contractValue >= 1_000_000_00 ? 82 : 55;

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::NewAccountHighValue,
            "new_account_high_value:{$user->id}",
            'Account < '.$days.' days with high-value contracts',
            [
                'account_age_days' => $user->created_at->diffInDays(now()),
                'contract_value_minor' => $contractValue,
            ],
            $score,
        );
    }

    private function scanSharedIpAccounts(): int
    {
        $created = 0;
        $hours = (int) config('user_activity_patrol.same_ip_hours', 168);
        $threshold = (int) config('user_activity_patrol.same_ip_account_threshold', 3);
        $since = now()->subHours($hours);

        $ips = LoginEvent::query()
            ->where('logged_in_at', '>=', $since)
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('COUNT(DISTINCT user_id) as user_count'))
            ->groupBy('ip_address')
            ->having('user_count', '>=', $threshold)
            ->pluck('user_count', 'ip_address');

        foreach ($ips as $ip => $userCount) {
            $userIds = LoginEvent::query()
                ->where('ip_address', $ip)
                ->where('logged_in_at', '>=', $since)
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $user = User::query()->find($userId);
                if ($user) {
                    $created += $this->upsertFlag(
                        $user,
                        UserActivityAnomalyType::SharedIpAccounts,
                        "shared_ip:{$user->id}:".md5((string) $ip),
                        "{$userCount} accounts on same IP recently",
                        [
                            'ip_address' => (string) $ip,
                            'ip_masked' => $this->maskIp((string) $ip),
                            'account_count' => (int) $userCount,
                        ],
                        (int) $userCount >= 4 ? 80 : 52,
                    ) ? 1 : 0;
                }
            }
        }

        return $created;
    }

    private function flagSharedIpForUser(User $user): bool
    {
        $latest = LoginEvent::query()->where('user_id', $user->id)->latest('logged_in_at')->first();
        if (! $latest?->ip_address) {
            return false;
        }

        $hours = (int) config('user_activity_patrol.same_ip_hours', 168);
        $threshold = (int) config('user_activity_patrol.same_ip_account_threshold', 3);
        $since = now()->subHours($hours);

        $userCount = LoginEvent::query()
            ->where('ip_address', $latest->ip_address)
            ->where('logged_in_at', '>=', $since)
            ->distinct('user_id')
            ->count('user_id');

        if ($userCount < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::SharedIpAccounts,
            "shared_ip:{$user->id}:".md5($latest->ip_address),
            "{$userCount} accounts on same IP recently",
            [
                'ip_address' => $latest->ip_address,
                'ip_masked' => $this->maskIp($latest->ip_address),
                'account_count' => $userCount,
            ],
            $userCount >= 4 ? 80 : 52,
        );
    }

    private function scanConversationFlags(): int
    {
        $created = 0;
        ConversationThreadReview::query()
            ->whereIn('status', ['open', 'under_review', 'escalated'])
            ->where('last_flagged_at', '>=', now()->subDays(30))
            ->with(['quest:id,client_id,freelancer_id,title'])
            ->chunk(50, function ($reviews) use (&$created): void {
                foreach ($reviews as $review) {
                    $created += $this->flagConversationReview($review) ? 1 : 0;
                }
            });

        return $created;
    }

    private function flagConversationForUser(User $user): bool
    {
        $questIds = Quest::query()
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->pluck('id');

        $review = ConversationThreadReview::query()
            ->whereIn('quest_id', $questIds)
            ->whereIn('status', ['open', 'under_review', 'escalated'])
            ->latest('last_flagged_at')
            ->first();

        if (! $review) {
            return false;
        }

        return $this->flagConversationReview($review, $user);
    }

    private function flagConversationReview(ConversationThreadReview $review, ?User $targetUser = null): bool
    {
        $review->loadMissing('quest');
        $quest = $review->quest;
        if (! $quest) {
            return false;
        }

        $categories = $review->trigger_categories ?? [];
        $isOffPlatform = collect($categories)->contains(fn ($c) => str_contains(strtolower((string) $c), 'payment') || str_contains(strtolower((string) $c), 'off_platform'));
        $type = $isOffPlatform ? UserActivityAnomalyType::OffPlatformPayment : UserActivityAnomalyType::ConversationFlag;

        $flaggedContent = ConversationMessageFlag::query()
            ->where('quest_conversation_thread_id', $review->quest_conversation_thread_id)
            ->latest()
            ->value('snippet');

        $users = collect([$quest->client_id, $quest->freelancer_id])->filter()->unique();
        $created = false;

        foreach ($users as $userId) {
            if ($targetUser && $targetUser->id !== $userId) {
                continue;
            }
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            $score = $isOffPlatform ? 58 : 28;
            $created = $this->upsertFlag(
                $user,
                $type,
                "{$type->value}:{$user->id}:review:{$review->id}",
                $isOffPlatform ? 'Off-platform payment reference in conversation' : 'Conversation policy violation',
                [
                    'review_id' => $review->id,
                    'quest_id' => $quest->id,
                    'quest_title' => $quest->title,
                    'categories' => $categories,
                    'snippet' => Str::limit((string) $flaggedContent, 120),
                ],
                $score,
            ) || $created;
        }

        return $created;
    }

    private function scanReviewVelocity(): int
    {
        $created = 0;
        $hours = (int) config('user_activity_patrol.review_velocity_hours', 24);
        $threshold = (int) config('user_activity_patrol.review_velocity_count', 5);
        $since = now()->subHours($hours);

        $reviewerIds = Review::query()
            ->where('created_at', '>=', $since)
            ->select('reviewer_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('reviewer_id')
            ->having('cnt', '>=', $threshold)
            ->pluck('cnt', 'reviewer_id');

        foreach ($reviewerIds as $userId => $count) {
            $user = User::query()->find($userId);
            if ($user) {
                $created += $this->flagReviewVelocityForUser($user, (int) $count) ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagReviewVelocityForUser(User $user, ?int $count = null): bool
    {
        $hours = (int) config('user_activity_patrol.review_velocity_hours', 24);
        $threshold = (int) config('user_activity_patrol.review_velocity_count', 5);
        $since = now()->subHours($hours);

        $count ??= Review::query()
            ->where('reviewer_id', $user->id)
            ->where('created_at', '>=', $since)
            ->count();

        if ($count < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::ReviewManipulation,
            "review_velocity:{$user->id}",
            "{$count} reviews submitted in {$hours}h",
            ['review_count' => $count, 'window_hours' => $hours],
            48,
        );
    }

    private function scanVerificationInconsistency(): int
    {
        $created = 0;
        UserVerification::query()
            ->where('status', 'approved')
            ->where('updated_at', '>=', now()->subDays(30))
            ->with('user')
            ->chunk(100, function ($verifications) use (&$created): void {
                foreach ($verifications as $verification) {
                    if ($verification->user) {
                        $created += $this->flagVerificationForUser($verification->user) ? 1 : 0;
                    }
                }
            });

        return $created;
    }

    private function flagVerificationForUser(User $user): bool
    {
        $legalName = Str::lower(trim($user->name ?? ''));
        $bvnVerification = UserVerification::query()
            ->where('user_id', $user->id)
            ->where('verification_type', 'bvn')
            ->where('status', 'approved')
            ->latest()
            ->first();
        $bankVerification = UserVerification::query()
            ->where('user_id', $user->id)
            ->whereIn('verification_type', ['bank_account', 'bank'])
            ->where('status', 'approved')
            ->latest()
            ->first();

        $bvnName = Str::lower(trim((string) data_get($bvnVerification?->metadata, 'account_name') ?? data_get($bvnVerification?->metadata, 'name') ?? ''));
        $bankName = Str::lower(trim((string) data_get($bankVerification?->metadata, 'account_name') ?? data_get($bankVerification?->metadata, 'name') ?? ''));

        $names = array_filter([$legalName, $bvnName, $bankName]);
        if (count($names) < 2) {
            return false;
        }

        $variance = $this->nameVariancePercent($names);
        if ($variance < 15) {
            return false;
        }

        $score = $variance >= 35 ? 55 : 38;

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::VerificationFail,
            "verification_inconsistency:{$user->id}",
            'Identity name variance detected ('.round($variance).'%)',
            [
                'kyc_name' => $user->name,
                'bvn_name' => $bvnName ?: null,
                'bank_name' => $bankName ?: null,
                'variance_percent' => round($variance, 1),
            ],
            $score,
        );
    }

    private function scanCancellationPatterns(): int
    {
        $created = 0;
        $days = (int) config('user_activity_patrol.cancellation_days', 14);
        $threshold = (int) config('user_activity_patrol.cancellation_threshold', 5);
        $since = now()->subDays($days);

        $freelancerIds = QuestContract::query()
            ->whereNotNull('cancelled_at')
            ->where('cancelled_at', '>=', $since)
            ->select('freelancer_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('freelancer_id')
            ->having('cnt', '>=', $threshold)
            ->pluck('cnt', 'freelancer_id');

        foreach ($freelancerIds as $userId => $count) {
            $user = User::query()->find($userId);
            if ($user) {
                $created += $this->flagCancellationForUser($user, (int) $count) ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagCancellationForUser(User $user, ?int $count = null): bool
    {
        $days = (int) config('user_activity_patrol.cancellation_days', 14);
        $threshold = (int) config('user_activity_patrol.cancellation_threshold', 5);
        $since = now()->subDays($days);

        $count ??= QuestContract::query()
            ->where('freelancer_id', $user->id)
            ->whereNotNull('cancelled_at')
            ->where('cancelled_at', '>=', $since)
            ->count();

        if ($count < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::CancellationPattern,
            "cancellation_pattern:{$user->id}",
            "{$count} contract cancellations in {$days} days",
            ['cancellation_count' => $count, 'window_days' => $days],
            52,
        );
    }

    private function scanWinRateAnomalies(): int
    {
        $created = 0;
        User::query()
            ->whereHas('questOffers')
            ->where('created_at', '>=', now()->subDays(60))
            ->chunk(100, function ($users) use (&$created): void {
                foreach ($users as $user) {
                    $created += $this->flagWinRateForUser($user) ? 1 : 0;
                }
            });

        return $created;
    }

    private function flagWinRateForUser(User $user): bool
    {
        $proposals = QuestOffer::query()->where('freelancer_id', $user->id)->count();
        $wins = Quest::query()->where('freelancer_id', $user->id)->whereNotNull('accepted_quest_offer_id')->count();

        if ($proposals < 5 || $wins < 5) {
            return false;
        }

        $rate = $wins / $proposals;
        if ($rate < 0.85) {
            return false;
        }

        if ($user->created_at->diffInDays(now()) > 90) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::WinRateAnomaly,
            "win_rate:{$user->id}",
            round($rate * 100).'% win rate on recent account',
            ['proposals' => $proposals, 'wins' => $wins, 'win_rate' => round($rate, 2)],
            54,
        );
    }

    private function scanPremiumAnomalies(): int
    {
        $created = 0;
        FreelancerSubscription::query()
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->with('user')
            ->chunk(50, function ($subs) use (&$created): void {
                foreach ($subs as $sub) {
                    if ($sub->user) {
                        $created += $this->flagPremiumForUser($sub->user) ? 1 : 0;
                    }
                }
            });

        return $created;
    }

    private function flagPremiumForUser(User $user): bool
    {
        $days = 14;
        if ($user->created_at->diffInDays(now()) >= $days) {
            return false;
        }

        $hasPremium = FreelancerSubscription::query()
            ->where('user_id', $user->id)
            ->where('tier', FreelancerSubscriptionTier::Pro->value)
            ->exists();

        if (! $hasPremium) {
            return false;
        }

        $payment = FreelancerSubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->latest('paid_at')
            ->first();

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::PremiumAnomaly,
            "premium_anomaly:{$user->id}",
            'Premium purchased on account < '.$days.' days old',
            [
                'account_age_days' => $user->created_at->diffInDays(now()),
                'payment_id' => $payment?->id,
            ],
            36,
        );
    }

    private function scanLowTrustScores(): int
    {
        $created = 0;
        User::query()
            ->whereHas('trustMetrics', fn ($q) => $q->where('freelancer_trust_score', '<=', 35)
                ->orWhere('client_trust_score', '<=', 35))
            ->chunk(100, function ($users) use (&$created): void {
                foreach ($users as $user) {
                    $created += $this->flagLowTrustForUser($user) ? 1 : 0;
                }
            });

        return $created;
    }

    private function flagLowTrustForUser(User $user): bool
    {
        $user->loadMissing('trustMetrics');
        $freelancerScore = (int) ($user->trustMetrics?->freelancer_trust_score ?? 100);
        $clientScore = (int) ($user->trustMetrics?->client_trust_score ?? 100);
        $lowest = min($freelancerScore, $clientScore);

        if ($lowest > 35) {
            return false;
        }

        $riskFromTrust = max(0, 100 - $lowest);
        if ($riskFromTrust < 30) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::TrustScoreDrop,
            "trust_score_drop:{$user->id}",
            'Trust score dropped to '.$lowest.'%',
            [
                'freelancer_trust_score' => $freelancerScore,
                'client_trust_score' => $clientScore,
            ],
            min(70, $riskFromTrust),
        );
    }

    private function autoResolveEligible(): int
    {
        $days = (int) config('user_activity_patrol.auto_resolve_low_risk_days', 14);
        $resolved = 0;

        UserActivityPatrolFlag::query()
            ->where('status', UserActivityPatrolStatus::Open->value)
            ->where('risk_level', UserActivityRiskLevel::Low->value)
            ->where('detected_at', '<', now()->subDays($days))
            ->update([
                'status' => UserActivityPatrolStatus::Resolved->value,
                'resolved_at' => now(),
            ]);

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function upsertFlag(
        User $user,
        UserActivityAnomalyType $type,
        string $fingerprint,
        string $summary,
        array $meta,
        int $riskScore,
    ): bool {
        if ($this->isExcludedStaffUser($user)) {
            return false;
        }

        $riskLevel = UserActivityRiskLevel::fromScore($riskScore);

        $existing = UserActivityPatrolFlag::query()->where('fingerprint', $fingerprint)->first();

        if ($existing) {
            if (! $existing->isOpen()) {
                return false;
            }

            $existing->forceFill([
                'risk_level' => $riskLevel->value,
                'risk_score' => $riskScore,
                'summary' => $summary,
                'meta' => array_merge($existing->meta ?? [], $meta),
                'detected_at' => now(),
            ])->save();

            $this->broadcast->dispatch('updated', $user->id, $existing->id);

            return false;
        }

        $flag = UserActivityPatrolFlag::query()->create([
            'user_id' => $user->id,
            'anomaly_type' => $type->value,
            'risk_level' => $riskLevel->value,
            'risk_score' => $riskScore,
            'status' => UserActivityPatrolStatus::Open->value,
            'fingerprint' => $fingerprint,
            'summary' => $summary,
            'meta' => $meta,
            'detected_at' => now(),
        ]);

        $this->broadcast->dispatch('created', $user->id, $flag->id);

        return true;
    }

    private function scanChargebacks(): int
    {
        $created = 0;
        $userIds = collect();

        PaymentReviewFlag::query()
            ->where('resolution_status', '!=', 'resolved')
            ->where(function ($q): void {
                $q->where('anomaly_type', 'like', '%chargeback%')
                    ->orWhere('anomaly_type', 'chargeback');
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->with('escrow:id,client_id,freelancer_id')
            ->chunk(50, function ($flags) use (&$userIds): void {
                foreach ($flags as $flag) {
                    if ($flag->escrow?->client_id) {
                        $userIds->push($flag->escrow->client_id);
                    }
                    if ($flag->escrow?->freelancer_id) {
                        $userIds->push($flag->escrow->freelancer_id);
                    }
                }
            });

        PaymentEscrow::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->where('meta->chargeback', true)
            ->select('client_id', 'freelancer_id')
            ->chunk(50, function ($escrows) use (&$userIds): void {
                foreach ($escrows as $e) {
                    $userIds->push($e->client_id, $e->freelancer_id);
                }
            });

        foreach ($userIds->filter()->unique() as $userId) {
            $user = User::query()->find($userId);
            if ($user) {
                $created += $this->flagChargebackForUser($user) ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagChargebackForUser(User $user): bool
    {
        $hasChargeback = PaymentReviewFlag::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->where(function ($q): void {
                $q->where('anomaly_type', 'like', '%chargeback%')->orWhere('anomaly_type', 'chargeback');
            })
            ->whereHas('escrow', fn ($e) => $e->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->exists();

        if (! $hasChargeback) {
            $hasChargeback = PaymentEscrow::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->where('meta->chargeback', true)
                ->where(function ($q) use ($user): void {
                    $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
                })
                ->exists();
        }

        if (! $hasChargeback) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::Chargeback,
            "chargeback:{$user->id}",
            'Payment processor chargeback on recent transaction',
            ['detected_via' => 'payment_review_or_escrow_meta'],
            85,
        );
    }

    private function scanRefundRates(): int
    {
        $created = 0;
        $days = (int) config('user_activity_patrol.refund_rate_days', 30);
        $since = now()->subDays($days);

        $userIds = PaymentEscrow::query()
            ->where('funded_at', '>=', $since)
            ->select('client_id', 'freelancer_id')
            ->get()
            ->flatMap(fn ($e) => [$e->client_id, $e->freelancer_id])
            ->filter()
            ->unique();

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if ($user) {
                $created += $this->flagRefundRateForUser($user) ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagRefundRateForUser(User $user): bool
    {
        $days = (int) config('user_activity_patrol.refund_rate_days', 30);
        $threshold = (int) config('user_activity_patrol.refund_rate_threshold_percent', 30);
        $since = now()->subDays($days);

        $total = PaymentEscrow::query()
            ->where('funded_at', '>=', $since)
            ->where(function ($q) use ($user): void {
                $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
            })
            ->count();

        if ($total < 3) {
            return false;
        }

        $refunded = PaymentEscrow::query()
            ->where('funded_at', '>=', $since)
            ->where(function ($q) use ($user): void {
                $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
            })
            ->where('refunded_minor', '>', 0)
            ->count();

        $rate = ($refunded / $total) * 100;
        if ($rate < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::RefundRateHigh,
            "refund_rate:{$user->id}",
            round($rate)."% refund/dispute rate in {$days} days",
            ['refund_rate_percent' => round($rate, 1), 'total_transactions' => $total, 'refunded_count' => $refunded],
            $rate >= 50 ? 62 : 45,
        );
    }

    private function scanPaymentMethodChanges(): int
    {
        $created = 0;
        $days = (int) config('user_activity_patrol.payment_method_change_days', 7);
        $since = now()->subDays($days);

        $userIds = FreelancerSubscriptionPayment::query()
            ->where('paid_at', '>=', $since)
            ->where('status', 'paid')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if ($user) {
                $created += $this->flagPaymentMethodChangeForUser($user) ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagPaymentMethodChangeForUser(User $user): bool
    {
        $days = (int) config('user_activity_patrol.payment_method_change_days', 7);
        $threshold = (int) config('user_activity_patrol.payment_method_change_threshold', 3);
        $since = now()->subDays($days);

        $fingerprints = FreelancerSubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->where('paid_at', '>=', $since)
            ->where('status', 'paid')
            ->get()
            ->map(fn ($p) => data_get($p->meta, 'card_fingerprint') ?? data_get($p->meta, 'authorization.last4').':'.data_get($p->meta, 'authorization.bank'))
            ->filter()
            ->unique();

        if ($fingerprints->count() < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::PaymentIssue,
            "payment_method_change:{$user->id}",
            "{$fingerprints->count()} payment methods in {$days} days",
            ['method_count' => $fingerprints->count(), 'window_days' => $days],
            55,
        );
    }

    private function scanSharedIdentity(): int
    {
        $created = 0;
        foreach (['nin', 'bvn'] as $field) {
            $duplicates = User::query()
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->select($field, DB::raw('COUNT(*) as cnt'))
                ->groupBy($field)
                ->having('cnt', '>', 1)
                ->pluck($field);

            foreach ($duplicates as $value) {
                User::query()->where($field, $value)->each(function (User $user) use (&$created, $field, $value): void {
                    $created += $this->upsertFlag(
                        $user,
                        UserActivityAnomalyType::SharedIdentity,
                        "shared_identity:{$field}:".md5((string) $value).":{$user->id}",
                        "Shared {$field} with other account(s)",
                        ['identity_field' => $field],
                        70,
                    ) ? 1 : 0;
                });
            }
        }

        return $created;
    }

    private function flagSharedIdentityForUser(User $user): bool
    {
        foreach (['nin', 'bvn'] as $field) {
            $value = $user->{$field};
            if (! $value) {
                continue;
            }
            $count = User::query()->where($field, $value)->count();
            if ($count > 1) {
                return $this->upsertFlag(
                    $user,
                    UserActivityAnomalyType::SharedIdentity,
                    "shared_identity:{$field}:".md5((string) $value).":{$user->id}",
                    "Shared {$field} with ".($count - 1).' other account(s)',
                    ['identity_field' => $field, 'account_count' => $count],
                    70,
                );
            }
        }

        return false;
    }

    private function scanSharedKycDocuments(): int
    {
        if (! Schema::hasTable('user_identity_documents')) {
            return 0;
        }

        $created = 0;

        $sharedHashes = UserIdentityDocument::query()
            ->whereNotNull('number_hash')
            ->where('number_hash', '!=', '')
            ->select('number_hash', DB::raw('COUNT(DISTINCT user_id) as account_count'))
            ->groupBy('number_hash')
            ->having('account_count', '>', 1)
            ->pluck('number_hash');

        foreach ($sharedHashes as $hash) {
            $documents = UserIdentityDocument::query()
                ->where('number_hash', $hash)
                ->get(['user_id', 'document_kind', 'normalized_last4']);
            $accountCount = $documents->pluck('user_id')->unique()->count();

            foreach ($documents->unique('user_id') as $document) {
                $user = User::query()->find($document->user_id);
                if ($user) {
                    $created += $this->upsertFlag(
                        $user,
                        UserActivityAnomalyType::SharedKycDocument,
                        'shared_kyc_document:'.md5((string) $hash).":{$user->id}",
                        'Same KYC document on '.$accountCount.' account(s)',
                        [
                            'document_kind' => $document->document_kind,
                            'document_last4' => $document->normalized_last4,
                            'account_count' => $accountCount,
                        ],
                        $accountCount >= 3 ? 85 : 72,
                    ) ? 1 : 0;
                }
            }
        }

        return $created;
    }

    private function flagSharedKycDocumentForUser(User $user): bool
    {
        if (! Schema::hasTable('user_identity_documents')) {
            return false;
        }

        $documents = UserIdentityDocument::query()
            ->where('user_id', $user->id)
            ->whereNotNull('number_hash')
            ->where('number_hash', '!=', '')
            ->get(['document_kind', 'number_hash', 'normalized_last4']);

        foreach ($documents as $document) {
            $accountCount = UserIdentityDocument::query()
                ->where('number_hash', $document->number_hash)
                ->distinct('user_id')
                ->count('user_id');

            if ($accountCount > 1) {
                return $this->upsertFlag(
                    $user,
                    UserActivityAnomalyType::SharedKycDocument,
                    'shared_kyc_document:'.md5((string) $document->number_hash).":{$user->id}",
                    'Same '.str_replace('_', ' ', (string) $document->document_kind).' KYC document on '.$accountCount.' account(s)',
                    [
                        'document_kind' => $document->document_kind,
                        'document_last4' => $document->normalized_last4,
                        'account_count' => $accountCount,
                    ],
                    $accountCount >= 3 ? 85 : 72,
                );
            }
        }

        return false;
    }

    private function scanEscrowRoundTripping(): int
    {
        $created = 0;
        $days = (int) config('user_activity_patrol.round_trip_window_days', 60);
        $since = now()->subDays($days);

        $clientIds = Quest::query()
            ->whereNotNull('escrow_funded_at')
            ->whereNotNull('funds_released_at')
            ->where('funds_released_at', '>=', $since)
            ->distinct()
            ->pluck('client_id')
            ->filter();

        foreach ($clientIds as $clientId) {
            $user = User::query()->find($clientId);
            if ($user) {
                $created += $this->flagEscrowRoundTrippingForUser($user) ? 1 : 0;
            }
        }

        return $created;
    }

    private function flagEscrowRoundTrippingForUser(User $user): bool
    {
        $days = (int) config('user_activity_patrol.round_trip_window_days', 60);
        $minReleases = (int) config('user_activity_patrol.round_trip_min_releases', 3);
        $maxMessages = (int) config('user_activity_patrol.round_trip_max_messages', 4);
        $since = now()->subDays($days);

        $quests = Quest::query()
            ->where('client_id', $user->id)
            ->whereNotNull('escrow_funded_at')
            ->whereNotNull('funds_released_at')
            ->where('funds_released_at', '>=', $since)
            ->get(['id', 'freelancer_id', 'paid_out_minor', 'budget_amount_minor']);

        if ($quests->count() < $minReleases) {
            return false;
        }

        $questIds = $quests->pluck('id')->all();

        $messagesByQuest = QuestConversationThread::query()
            ->whereIn('quest_id', $questIds)
            ->select('quest_id', DB::raw('SUM(messages_count) as message_total'))
            ->groupBy('quest_id')
            ->pluck('message_total', 'quest_id');

        $deliverablesByQuest = QuestContract::query()
            ->whereIn('quest_id', $questIds)
            ->withCount('deliverables')
            ->get()
            ->groupBy('quest_id')
            ->map(fn ($group) => (int) $group->sum('deliverables_count'));

        $lowEngagement = $quests->filter(function (Quest $quest) use ($messagesByQuest, $deliverablesByQuest, $maxMessages) {
            $messages = (int) ($messagesByQuest[$quest->id] ?? 0);
            $deliverables = (int) ($deliverablesByQuest[$quest->id] ?? 0);

            return $deliverables === 0 && $messages <= $maxMessages;
        });

        if ($lowEngagement->count() < $minReleases) {
            return false;
        }

        $distinctFreelancers = $lowEngagement->pluck('freelancer_id')->filter()->unique();
        $totalMinor = (int) $lowEngagement->sum(fn (Quest $quest) => (int) ($quest->paid_out_minor ?: $quest->budget_amount_minor));

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::EscrowRoundTripping,
            "escrow_round_tripping:{$user->id}",
            $lowEngagement->count().' escrow releases with no deliverables in '.$days.' days',
            [
                'low_engagement_releases' => $lowEngagement->count(),
                'distinct_freelancers' => $distinctFreelancers->count(),
                'total_value_minor' => $totalMinor,
                'window_days' => $days,
            ],
            $distinctFreelancers->count() <= 2 ? 88 : 66,
        );
    }

    private function scanLocationAnomalies(): int
    {
        $created = 0;
        $hours = (int) config('user_activity_patrol.location_subnet_hours', 24);
        $threshold = (int) config('user_activity_patrol.location_subnet_change_threshold', 2);
        $since = now()->subHours($hours);

        LoginEvent::query()
            ->where('logged_in_at', '>=', $since)
            ->orderBy('user_id')
            ->chunk(200, function ($events) use (&$created, $threshold): void {
                $byUser = $events->groupBy('user_id');
                foreach ($byUser as $userId => $userEvents) {
                    $subnets = $userEvents->map(fn ($e) => $this->ipSubnet((string) $e->ip_address))->filter()->unique();
                    if ($subnets->count() >= $threshold) {
                        $user = User::query()->find($userId);
                        if ($user) {
                            $created += $this->flagLocationAnomalyForUser($user, $subnets->count()) ? 1 : 0;
                        }
                    }
                }
            });

        return $created;
    }

    private function flagLocationAnomalyForUser(User $user, ?int $subnetCount = null): bool
    {
        $hours = (int) config('user_activity_patrol.location_subnet_hours', 24);
        $threshold = (int) config('user_activity_patrol.location_subnet_change_threshold', 2);
        $since = now()->subHours($hours);

        if ($subnetCount === null) {
            $subnets = LoginEvent::query()
                ->where('user_id', $user->id)
                ->where('logged_in_at', '>=', $since)
                ->pluck('ip_address')
                ->map(fn ($ip) => $this->ipSubnet((string) $ip))
                ->filter()
                ->unique();
            $subnetCount = $subnets->count();
        }

        if ($subnetCount < $threshold) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::LocationAnomaly,
            "location_anomaly:{$user->id}",
            "{$subnetCount} distinct network regions in {$hours}h (possible VPN/spoofing)",
            ['subnet_count' => $subnetCount, 'window_hours' => $hours],
            52,
        );
    }

    private function scanDeviceAnomalies(): int
    {
        $created = 0;
        $inactivityDays = (int) config('user_activity_patrol.device_inactivity_days', 180);

        User::query()
            ->whereHas('loginEvents')
            ->chunk(100, function ($users) use (&$created, $inactivityDays): void {
                foreach ($users as $user) {
                    $created += $this->flagDeviceAnomalyForUser($user, $inactivityDays) ? 1 : 0;
                }
            });

        return $created;
    }

    private function flagDeviceAnomalyForUser(User $user, ?int $inactivityDays = null): bool
    {
        $inactivityDays ??= (int) config('user_activity_patrol.device_inactivity_days', 180);

        $events = LoginEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('logged_in_at')
            ->limit(2)
            ->get();

        if ($events->count() < 2) {
            return false;
        }

        [$latest, $previous] = $events;
        $gapDays = $previous->logged_in_at?->diffInDays($latest->logged_in_at) ?? 0;

        if ($gapDays < $inactivityDays) {
            return false;
        }

        $uaChanged = $latest->user_agent && $previous->user_agent
            && $latest->user_agent !== $previous->user_agent;

        if (! $uaChanged) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::DeviceAnomaly,
            "device_anomaly:{$user->id}",
            'New device login after '.round($gapDays).' days inactivity',
            ['inactive_days' => $gapDays, 'previous_login' => $previous->logged_in_at?->toIso8601String()],
            48,
        );
    }

    private function scanReciprocalReviews(): int
    {
        $created = 0;
        $hours = (int) config('user_activity_patrol.reciprocal_review_hours', 6);
        $since = now()->subDays(14);

        $signals = ReviewAuthenticitySignal::query()
            ->where('signal_type', 'reciprocal_pair')
            ->where('created_at', '>=', $since)
            ->with('review:id,reviewer_id,reviewee_id')
            ->get();

        foreach ($signals as $signal) {
            foreach ([$signal->review?->reviewer_id, $signal->review?->reviewee_id] as $userId) {
                if ($userId) {
                    $user = User::query()->find($userId);
                    if ($user) {
                        $created += $this->flagReciprocalForUser($user) ? 1 : 0;
                    }
                }
            }
        }

        Review::query()
            ->where('created_at', '>=', $since)
            ->chunk(100, function ($reviews) use (&$created, $hours): void {
                foreach ($reviews as $review) {
                    $counter = Review::query()
                        ->where('reviewer_id', $review->reviewee_id)
                        ->where('reviewee_id', $review->reviewer_id)
                        ->whereBetween('created_at', [
                            $review->created_at->copy()->subHours($hours),
                            $review->created_at->copy()->addHours($hours),
                        ])
                        ->exists();
                    if ($counter) {
                        foreach ([$review->reviewer_id, $review->reviewee_id] as $userId) {
                            $user = User::query()->find($userId);
                            if ($user) {
                                $created += $this->flagReciprocalForUser($user) ? 1 : 0;
                            }
                        }
                    }
                }
            });

        return $created;
    }

    private function flagReciprocalForUser(User $user): bool
    {
        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::ReviewManipulation,
            "reciprocal_reviews:{$user->id}",
            'Reciprocal review exchange detected within short window',
            ['pattern' => 'reciprocal_pair'],
            50,
        );
    }

    private function scanSentimentMismatch(): int
    {
        $created = 0;
        Review::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('sentiment_score')
            ->where(function ($q): void {
                $q->where(function ($qq): void {
                    $qq->where('rating', '<=', 2)->where('sentiment_score', '>', 0.55);
                })->orWhere(function ($qq): void {
                    $qq->where('rating', '>=', 4)->where('sentiment_score', '<', 0.25);
                });
            })
            ->select('reviewer_id', 'reviewee_id')
            ->chunk(100, function ($reviews) use (&$created): void {
                foreach ($reviews as $review) {
                    foreach ([$review->reviewer_id, $review->reviewee_id] as $userId) {
                        $user = User::query()->find($userId);
                        if ($user) {
                            $created += $this->flagSentimentMismatchForUser($user) ? 1 : 0;
                        }
                    }
                }
            });

        return $created;
    }

    private function flagSentimentMismatchForUser(User $user): bool
    {
        $mismatch = Review::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->where(function ($q) use ($user): void {
                $q->where('reviewer_id', $user->id)->orWhere('reviewee_id', $user->id);
            })
            ->whereNotNull('sentiment_score')
            ->where(function ($q): void {
                $q->where(function ($qq): void {
                    $qq->where('rating', '<=', 2)->where('sentiment_score', '>', 0.55);
                })->orWhere(function ($qq): void {
                    $qq->where('rating', '>=', 4)->where('sentiment_score', '<', 0.25);
                });
            })
            ->exists();

        if (! $mismatch) {
            return false;
        }

        return $this->upsertFlag(
            $user,
            UserActivityAnomalyType::ReviewManipulation,
            "sentiment_mismatch:{$user->id}",
            'Review sentiment does not match star rating (possible coercion)',
            ['pattern' => 'sentiment_mismatch'],
            46,
        );
    }

    private function ipSubnet(string $ip): ?string
    {
        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return null;
        }

        return $parts[0].'.'.$parts[1];
    }

    /**
     * @param  list<string>  $names
     */
    private function nameVariancePercent(array $names): float
    {
        $maxLen = max(array_map(fn ($n) => strlen($n), $names));
        if ($maxLen === 0) {
            return 0;
        }

        $minSimilarity = 100;
        for ($i = 0; $i < count($names); $i++) {
            for ($j = $i + 1; $j < count($names); $j++) {
                similar_text($names[$i], $names[$j], $percent);
                $minSimilarity = min($minSimilarity, $percent);
            }
        }

        return 100 - $minSimilarity;
    }

    private function maskIp(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0].'.'.$parts[1].'.xx.xxx';
        }

        return Str::limit($ip, 8, '…');
    }

    private function scanAccountInconsistency(): int
    {
        $created = 0;
        $thresholdMinor = 2_000_000_00;

        User::query()
            ->where('verification_tier', '<=', 2)
            ->whereHas('questOffers', fn ($q) => $q->whereHas('quest', fn ($qq) => $qq->where('budget_amount_minor', '>=', $thresholdMinor)))
            ->chunk(50, function ($users) use (&$created, $thresholdMinor): void {
                foreach ($users as $user) {
                    $created += $this->upsertFlag(
                        $user,
                        UserActivityAnomalyType::AccountInconsistency,
                        "account_inconsistency:{$user->id}",
                        'Tier '.($user->verification_tier ?? 0).' activity on high-value quests',
                        ['tier' => $user->verification_tier, 'threshold_minor' => $thresholdMinor],
                        44,
                    ) ? 1 : 0;
                }
            });

        return $created;
    }

    public function flagClientAwardCancellation(User $client, QuestOffer $offer, Quest $quest, ?string $reason = null): bool
    {
        return $this->upsertFlag(
            $client,
            UserActivityAnomalyType::CancellationPattern,
            "client_award_cancelled:{$offer->id}",
            "Client cancelled award on “{$quest->title}” before escrow was funded",
            [
                'quest_id' => $quest->id,
                'offer_id' => $offer->id,
                'freelancer_id' => $offer->freelancer_id,
                'reason' => $reason,
            ],
            48,
        );
    }

    private function isExcludedStaffUser(User $user): bool
    {
        $user->loadMissing('role:id,slug');

        return in_array($user->role?->slug, self::EXCLUDED_ROLE_SLUGS, true);
    }
}
