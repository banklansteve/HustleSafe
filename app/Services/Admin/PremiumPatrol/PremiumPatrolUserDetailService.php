<?php

namespace App\Services\Admin\PremiumPatrol;

use App\Enums\FreelancerSubscriptionStatus;
use App\Enums\FreelancerSubscriptionTier;
use App\Enums\PremiumPatrolFlagStatus;
use App\Enums\PremiumPatrolFlagType;
use App\Enums\PremiumPatrolSubjectType;
use App\Enums\QuestDisputeStatus;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\LoginEvent;
use App\Models\PremiumPatrolAction;
use App\Models\PremiumPatrolFlag;
use App\Models\PremiumPatrolInvestigation;
use App\Models\PremiumPatrolWatchlist;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Admin\AdvancedUserManagementService;
use App\Support\NgnMoney;
use Illuminate\Support\Str;

final class PremiumPatrolUserDetailService
{
    public function __construct(
        private readonly AdvancedUserManagementService $userManagement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $user->loadMissing(['role:id,name,slug', 'stateModel:id,name']);

        $overviewPayload = $this->userManagement->profile($user, 'overview');
        $disputesPayload = $this->userManagement->profile($user, 'disputes');
        $overview = $overviewPayload['overview'];
        $userRow = $overview['user'];

        $subscription = FreelancerSubscription::query()->where('user_id', $user->id)->first();
        $payments = FreelancerSubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        $flags = PremiumPatrolFlag::query()
            ->where('subject_type', PremiumPatrolSubjectType::PremiumUser->value)
            ->where('subject_id', $user->id)
            ->orderByDesc('detected_at')
            ->get();

        $patrolRisk = $this->patrolRiskFromFlags($flags, $user);
        $trustScore = (int) ($userRow['trust_score'] ?? 0);
        $displayRisk = min(100, max($patrolRisk, 100 - $trustScore));

        $watchlist = PremiumPatrolWatchlist::query()
            ->with('addedBy:id,name')
            ->where('watchlist_type', 'premium')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $since30 = now()->subDays(30);
        $proposals30d = QuestOffer::query()->where('freelancer_id', $user->id)->where('created_at', '>=', $since30)->count();
        $contractsWon = Quest::query()->where('freelancer_id', $user->id)->whereNotNull('accepted_quest_offer_id')->where('created_at', '>=', $since30)->count();
        $jobsCompleted = Quest::query()->where('freelancer_id', $user->id)->whereNotNull('completed_at')->where('completed_at', '>=', $since30)->count();
        $earnings30d = (int) Quest::query()->where('freelancer_id', $user->id)->whereNotNull('completed_at')->where('completed_at', '>=', $since30)->sum('paid_out_minor');

        $lastProposal = QuestOffer::query()->where('freelancer_id', $user->id)->latest()->first();
        $lastContract = Quest::query()->where('freelancer_id', $user->id)->whereNotNull('accepted_quest_offer_id')->latest('updated_at')->first();
        $lastEarning = Quest::query()->where('freelancer_id', $user->id)->whereNotNull('completed_at')->where('paid_out_minor', '>', 0)->latest('completed_at')->first();

        return [
            'header' => [
                'user_id' => $user->id,
                'fullname' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'location' => trim(($user->city ?? '').($user->stateModel?->name ? ', '.$user->stateModel->name : '')) ?: '—',
                'account_status' => $userRow['account_status'] ?? 'active',
                'account_status_label' => Str::headline((string) ($userRow['account_status'] ?? 'active')),
                'trust_score' => $trustScore,
                'trust_band' => $userRow['trust_band'] ?? 'amber',
                'verification_tier' => (int) ($user->verification_tier ?? 0),
                'profile_url' => route('admin.users.index', ['q' => $user->email], false),
                'messenger_url' => route('admin.api.messenger.open', $user->id, false),
            ],
            'subscription' => $this->subscriptionCard($subscription, $payments->first()),
            'verification_timeline' => $this->verificationTimeline($user, $overview),
            'risk' => $this->riskAssessment($displayRisk, $patrolRisk, $trustScore, $flags, $user, $overview),
            'activity' => [
                'proposals_30d' => $proposals30d,
                'contracts_won_30d' => $contractsWon,
                'jobs_completed_30d' => $jobsCompleted,
                'earnings_30d_display' => NgnMoney::format($earnings30d),
                'is_premium' => $subscription?->isProActive() ?? false,
                'last_proposal_at' => $lastProposal?->created_at?->toIso8601String(),
                'last_contract_at' => $lastContract?->updated_at?->toIso8601String(),
                'last_earning' => $lastEarning ? [
                    'at' => $lastEarning->completed_at?->toIso8601String(),
                    'amount_display' => NgnMoney::format((int) $lastEarning->paid_out_minor),
                    'quest_title' => $lastEarning->title,
                ] : null,
            ],
            'disputes' => [
                'count' => count($disputesPayload['tabData'] ?? []),
                'open_count' => (int) ($userRow['open_disputes_count'] ?? 0),
                'items' => collect($disputesPayload['tabData'] ?? [])->map(fn ($d) => [
                    'id' => $d['id'],
                    'quest_title' => $d['quest'] ?? '—',
                    'status' => $d['status'] ?? '—',
                    'opened_by' => $d['opened_by'] ?? '—',
                    'amount_display' => $d['amount'] ?? '—',
                    'outcome' => $d['outcome'] ?? null,
                    'created_at' => $d['created_at'] ?? null,
                ])->values()->all(),
                'payment_issues' => [],
                'complaints' => [],
            ],
            'purchase_history' => $this->purchaseHistory($payments),
            'watchlist' => $watchlist ? [
                'id' => $watchlist->id,
                'reason' => $watchlist->reason,
                'added_by' => $watchlist->addedBy?->name,
                'added_at' => $watchlist->created_at?->toIso8601String(),
                'expires_at' => $watchlist->expires_at?->toIso8601String(),
                'status' => 'active',
            ] : null,
            'identity' => $this->identitySection($user, $overview),
            'devices' => $this->deviceHistory($user),
            'related_accounts' => $this->relatedAccounts($user),
            'flags' => $flags->map(fn (PremiumPatrolFlag $f) => [
                'id' => $f->id,
                'flag_type' => $f->flag_type,
                'label' => PremiumPatrolFlagType::from($f->flag_type)->label(),
                'severity' => $f->severity,
                'status' => $f->status,
                'detected_at' => $f->detected_at?->toIso8601String(),
            ])->values()->all(),
            'actions' => PremiumPatrolAction::query()
                ->where('subject_type', PremiumPatrolSubjectType::PremiumUser->value)
                ->where('subject_id', $user->id)
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->with('actor:id,name')
                ->get()
                ->map(fn ($a) => [
                    'action_type' => $a->action_type,
                    'actor' => $a->actor?->name,
                    'reason_notes' => $a->reason_notes,
                    'occurred_at' => $a->occurred_at?->toIso8601String(),
                ])->values()->all(),
            'investigations' => PremiumPatrolInvestigation::query()
                ->where('subject_type', PremiumPatrolSubjectType::PremiumUser->value)
                ->where('subject_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'case_reference' => $c->case_reference,
                    'status' => $c->status,
                    'title' => $c->title,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subscriptionCard(?FreelancerSubscription $subscription, ?FreelancerSubscriptionPayment $latestPayment): ?array
    {
        if (! $subscription) {
            return null;
        }

        $isActive = $subscription->isProActive();
        $daysRemaining = $subscription->renewal_date?->isFuture()
            ? (int) now()->diffInDays($subscription->renewal_date, false)
            : 0;

        $monthlyMinor = (int) $subscription->monthly_price_minor;
        $snapshot = $subscription->payment_method_snapshot ?? [];

        return [
            'plan_label' => $subscription->tier === FreelancerSubscriptionTier::Pro->value ? 'Premium' : 'Free',
            'billing_label' => $subscription->billing_cycle === 'year' ? 'Annual' : 'Monthly',
            'cost_display' => $subscription->billing_cycle === 'year'
                ? NgnMoney::format((int) $subscription->annual_price_minor).'/year'
                : NgnMoney::format($monthlyMinor).'/month',
            'signup_date' => $subscription->started_at?->toIso8601String(),
            'renewal_date' => $subscription->renewal_date?->toIso8601String(),
            'auto_renew' => (bool) $subscription->auto_renew,
            'status' => $subscription->status,
            'status_label' => $subscription->statusEnum()->label(),
            'is_active' => $isActive,
            'days_remaining' => max(0, $daysRemaining),
            'subscriber_id' => 'SUB-'.str_pad((string) $subscription->id, 7, '0', STR_PAD_LEFT),
            'payment_provider' => data_get($latestPayment?->meta, 'gateway.channel') ? 'Paystack' : (data_get($snapshot, 'channel') ? 'Paystack' : '—'),
            'card_last4' => data_get($snapshot, 'last4'),
            'card_brand' => data_get($snapshot, 'brand'),
            'total_spent_display' => NgnMoney::format((int) $subscription->total_spent_minor),
            'admin_suspended' => $subscription->status === FreelancerSubscriptionStatus::AdminSuspended->value,
            'suspension_reason' => $subscription->admin_suspension_reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return list<array<string, mixed>>
     */
    private function verificationTimeline(User $user, array $overview): array
    {
        $items = [];

        $items[] = [
            'key' => 'email',
            'label' => 'Email Verified',
            'status' => $user->email_verified_at ? 'valid' : 'pending',
            'verified_at' => $user->email_verified_at?->toIso8601String(),
            'detail' => $user->email_verified_at ? 'Status: Valid' : 'Pending verification',
        ];

        foreach ($overview['verification'] ?? [] as $verification) {
            $items[] = [
                'key' => 'verification_'.$verification['id'],
                'label' => Str::headline(str_replace('_', ' ', (string) ($verification['category'] ?? 'Verification'))),
                'status' => ($verification['status'] ?? '') === 'approved' ? 'valid' : ($verification['status'] ?? 'pending'),
                'verified_at' => $verification['reviewed_at'] ?? $verification['submitted_at'],
                'detail' => collect([
                    $verification['reviewer'] ? 'Approved by: '.$verification['reviewer'] : null,
                    ($verification['status'] ?? '') !== 'approved' && ($verification['rejection_reason'] ?? '') !== ''
                        ? 'Reason: '.$verification['rejection_reason']
                        : 'Status: '.Str::headline((string) ($verification['status'] ?? 'pending')),
                ])->filter()->implode(' | '),
            ];
        }

        if ($user->nin) {
            $items[] = [
                'key' => 'nin',
                'label' => 'NIN on file',
                'status' => 'valid',
                'verified_at' => null,
                'detail' => 'NIN recorded on profile',
            ];
        }

        if ($user->bvn) {
            $items[] = [
                'key' => 'bvn',
                'label' => 'BVN on file',
                'status' => 'valid',
                'verified_at' => null,
                'detail' => 'BVN recorded on profile',
            ];
        }

        return $items;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PremiumPatrolFlag>  $flags
     * @param  array<string, mixed>  $overview
     * @return array<string, mixed>
     */
    private function riskAssessment(int $displayRisk, int $patrolRisk, int $trustScore, $flags, User $user, array $overview): array
    {
        $openFlags = $flags->where('status', PremiumPatrolFlagStatus::Open->value);
        $accountAgeDays = (int) $user->created_at?->diffInDays(now());
        $disputes7d = QuestDispute::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('quest', fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->count();

        $proposals2h = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->where('created_at', '>=', now()->subHours(2))
            ->count();

        $uniqueIps24h = LoginEvent::query()
            ->where('user_id', $user->id)
            ->where('logged_in_at', '>=', now()->subDay())
            ->distinct('ip_address')
            ->count('ip_address');

        $factors = [
            [
                'ok' => $accountAgeDays >= 7,
                'label' => 'Account age: '.$accountAgeDays.' days'.($accountAgeDays < 7 ? ' (NEW ACCOUNT PREMIUM)' : ' (adequate)'),
            ],
            [
                'ok' => collect($overview['verification'] ?? [])->where('status', 'approved')->isNotEmpty() || $user->email_verified_at,
                'label' => 'Verification: '.(collect($overview['verification'] ?? [])->where('status', 'approved')->isNotEmpty() ? 'Complete' : 'Incomplete'),
            ],
            [
                'ok' => $disputes7d === 0,
                'label' => 'Dispute history: '.($disputes7d === 0 ? 'Zero disputes (7d)' : "{$disputes7d} disputes in last 7 days"),
            ],
            [
                'ok' => $uniqueIps24h <= 2,
                'label' => 'IP consistency: '.($uniqueIps24h <= 2 ? 'Stable' : "{$uniqueIps24h} different IPs in 24 hours"),
            ],
            [
                'ok' => $proposals2h < 6,
                'label' => 'Activity pattern: '.($proposals2h < 6 ? 'Normal' : "{$proposals2h} proposals in 2 hours (unusual velocity)"),
            ],
        ];

        $level = $displayRisk >= 50 ? 'high' : ($displayRisk >= 30 ? 'medium' : 'low');

        return [
            'score' => $displayRisk,
            'patrol_risk' => $patrolRisk,
            'trust_score' => $trustScore,
            'level' => $level,
            'level_label' => strtoupper($level).' RISK',
            'factors' => $factors,
            'flagged' => $openFlags->isNotEmpty(),
            'flag_summary' => $openFlags->isNotEmpty()
                ? 'FLAGGED: '.PremiumPatrolFlagType::from($openFlags->first()->flag_type)->label().'. Consider investigation.'
                : 'No anomalies detected.',
            'open_flags_count' => $openFlags->count(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FreelancerSubscriptionPayment>  $payments
     * @return list<array<string, mixed>>
     */
    private function purchaseHistory($payments): array
    {
        $seen = [];
        $rows = [];

        foreach ($payments as $payment) {
            $dup = isset($seen[$payment->amount_minor]) && $seen[$payment->amount_minor]->diffInHours($payment->paid_at) < 48;
            $seen[$payment->amount_minor] = $payment->paid_at;

            $rows[] = [
                'id' => $payment->id,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'amount_display' => NgnMoney::format((int) $payment->amount_minor),
                'billing_label' => $payment->billing_cycle === 'year' ? 'Annual Premium' : 'Monthly Premium',
                'payment_provider' => 'Paystack',
                'status' => $payment->status,
                'reference' => $payment->paystack_reference,
                'possible_duplicate' => $dup,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return array<string, mixed>
     */
    private function identitySection(User $user, array $overview): array
    {
        $govId = collect($overview['verification'] ?? [])->first(fn ($v) => str_contains(strtolower((string) ($v['category'] ?? '')), 'government') || str_contains(strtolower((string) ($v['category'] ?? '')), 'identity'));

        return [
            'government_id' => $govId ? [
                'type' => Str::headline(str_replace('_', ' ', (string) ($govId['category'] ?? 'ID'))),
                'status' => $govId['status'] ?? 'pending',
                'reviewed_at' => $govId['reviewed_at'] ?? null,
                'reviewer' => $govId['reviewer'] ?? null,
            ] : null,
            'selfie' => collect($overview['verification'] ?? [])->first(fn ($v) => str_contains(strtolower((string) ($v['category'] ?? '')), 'selfie') || str_contains(strtolower((string) ($v['category'] ?? '')), 'liveness')),
            'name' => $user->name,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deviceHistory(User $user): array
    {
        return LoginEvent::query()
            ->where('user_id', $user->id)
            ->where('logged_in_at', '>=', now()->subDays(30))
            ->orderByDesc('logged_in_at')
            ->limit(50)
            ->get()
            ->groupBy(fn (LoginEvent $e) => ($e->user_agent ?? 'unknown').'|'.($e->ip_address ?? 'unknown'))
            ->map(function ($group, $key) use ($user) {
                $parts = explode('|', (string) $key, 2);
                $proposals = QuestOffer::query()
                    ->where('freelancer_id', $user->id)
                    ->where('created_at', '>=', $group->min('logged_in_at'))
                    ->count();

                return [
                    'device' => Str::limit($parts[0] ?? 'Unknown device', 60),
                    'ip_masked' => $this->maskIp($parts[1] ?? ''),
                    'first_seen' => $group->min('logged_in_at')?->toIso8601String(),
                    'last_seen' => $group->max('logged_in_at')?->toIso8601String(),
                    'activity_count' => $proposals,
                    'location_warning' => false,
                ];
            })
            ->values()
            ->take(5)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relatedAccounts(User $user): array
    {
        $ips = LoginEvent::query()
            ->where('user_id', $user->id)
            ->where('logged_in_at', '>=', now()->subDays(30))
            ->pluck('ip_address')
            ->filter()
            ->unique()
            ->take(5);

        if ($ips->isEmpty()) {
            return [];
        }

        $relatedUserIds = LoginEvent::query()
            ->whereIn('ip_address', $ips)
            ->where('user_id', '!=', $user->id)
            ->where('logged_in_at', '>=', now()->subDays(30))
            ->pluck('user_id')
            ->unique()
            ->take(10);

        return User::query()
            ->whereIn('id', $relatedUserIds)
            ->get()
            ->map(function (User $related) {
                $sub = FreelancerSubscription::query()->where('user_id', $related->id)->first();

                return [
                    'user_id' => $related->id,
                    'name' => $related->name,
                    'username' => $related->username,
                    'verification_tier' => (int) ($related->verification_tier ?? 0),
                    'premium_active' => $sub?->isProActive() ?? false,
                    'trust_score' => (int) ($related->trust_score ?? 0),
                    'risk_score' => max(0, 100 - (int) ($related->trust_score ?? 0)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PremiumPatrolFlag>  $flags
     */
    private function patrolRiskFromFlags($flags, User $user): int
    {
        $score = 0;
        foreach ($flags as $flag) {
            if ($flag->status !== PremiumPatrolFlagStatus::Open->value) {
                continue;
            }
            $score += match ($flag->severity) {
                'high' => 40,
                'medium' => 25,
                default => 10,
            };
        }

        $latestPayment = FreelancerSubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->first();

        if ($latestPayment?->paid_at && $user->created_at->diffInDays($latestPayment->paid_at) < 7) {
            $score += 20;
        }

        return min(100, $score);
    }

    private function maskIp(string $ip): string
    {
        if ($ip === '') {
            return '—';
        }

        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.xxx';
        }

        return Str::limit($ip, 12).'…';
    }
}
