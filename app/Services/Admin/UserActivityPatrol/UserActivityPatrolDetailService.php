<?php

namespace App\Services\Admin\UserActivityPatrol;

use App\Enums\QuestDisputeStatus;
use App\Enums\UserActivityAnomalyType;
use App\Enums\UserActivityPatrolStatus;
use App\Enums\UserActivityRiskLevel;
use App\Models\ActivityLog;
use App\Models\ConversationMessageFlag;
use App\Models\ConversationThreadReview;
use App\Models\LoginEvent;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserActivityPatrolAction;
use App\Models\UserVerification;
use App\Models\UserActivityPatrolFlag;
use App\Models\UserActivityPatrolNote;
use App\Models\PaymentEscrow;
use App\Models\Review;
use App\Models\ReviewAuthenticitySignal;
use App\Services\Admin\AdvancedUserManagementService;
use App\Services\Verification\UserVerificationPresentationService;
use App\Support\NgnMoney;
use Illuminate\Support\Str;

final class UserActivityPatrolDetailService
{
    public function __construct(
        private readonly AdvancedUserManagementService $userManagement,
        private readonly UserVerificationPresentationService $verificationPresentation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?UserActivityPatrolFlag $primaryFlag = null, bool $isSuperAdmin = false): array
    {
        $user->loadMissing(['role:id,name,slug', 'stateModel:id,name', 'trustMetrics']);

        $overviewPayload = $this->userManagement->profile($user, 'overview');
        $disputesPayload = $this->userManagement->profile($user, 'disputes');
        $overview = $overviewPayload['overview'];
        $userRow = $overview['user'];

        $flags = UserActivityPatrolFlag::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                UserActivityPatrolStatus::Open->value,
                UserActivityPatrolStatus::UnderReview->value,
                UserActivityPatrolStatus::Watchlisted->value,
            ])
            ->orderByDesc('risk_score')
            ->orderByDesc('detected_at')
            ->get();

        $primary = $primaryFlag ?? $flags->first();
        if ($primary) {
            $primary->loadMissing('assignedTo');
        }
        $riskScore = $primary ? (int) $primary->risk_score : 0;
        $trustScore = (int) ($userRow['trust_score'] ?? 0);
        $displayRisk = min(100, max($riskScore, 100 - $trustScore));

        $completedJobs = Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();
        $earnedMinor = (int) Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereNotNull('completed_at')
            ->sum('paid_out_minor');

        return [
            'header' => [
                'user_id' => $user->id,
                'fullname' => $user->name,
                'username' => $user->username,
                'email' => $isSuperAdmin ? $user->email : $this->maskEmail($user->email),
                'avatar_url' => $user->avatar_url,
                'tier' => (int) ($user->verification_tier ?? 0),
                'tier_label' => 'Tier '.($user->verification_tier ?? 0),
                'location' => trim(($user->city ?? '').($user->stateModel?->name ? ', '.$user->stateModel->name : '')) ?: '—',
                'account_age_days' => $user->created_at->diffInDays(now()),
                'account_status' => $userRow['account_status'] ?? 'active',
                'risk_score' => $displayRisk,
                'risk_level' => UserActivityRiskLevel::fromScore($displayRisk)->value,
                'rating' => $user->avg_rating_as_freelancer,
                'ratings_count' => $user->ratings_count_as_freelancer,
                'completed_jobs' => $completedJobs,
                'earned_display' => NgnMoney::format($earnedMinor),
                'assigned_to' => $primary?->assignedTo?->only(['id', 'name']),
                'flag_id' => $primary?->id,
                'status' => $primary?->status,
                'status_label' => $primary
                    ? (UserActivityPatrolStatus::tryFrom($primary->status)?->label() ?? $primary->status)
                    : null,
                'latest_login_ip' => $isSuperAdmin
                    ? (LoginEvent::query()->where('user_id', $user->id)->latest('logged_in_at')->value('ip_address') ?: null)
                    : null,
            ],
            'anomaly_summary' => $primary ? $this->anomalySummary($primary, $flags, $isSuperAdmin) : null,
            'verification' => $this->verificationSection($user, $isSuperAdmin),
            'disputes' => $this->disputesSection($user, $disputesPayload),
            'timeline' => $this->activityTimeline($user),
            'transactions' => $this->transactionsSection($user, $isSuperAdmin),
            'related_accounts' => $this->relatedAccounts($user, $isSuperAdmin),
            'conversation_flags' => $this->conversationFlags($user),
            'moderation_history' => $this->moderationHistory($user, $flags),
            'review_signals' => $this->reviewSignals($user),
            'reversible_transactions' => $isSuperAdmin ? $this->reversibleTransactions($user) : [],
            'merge_candidates' => $isSuperAdmin ? ($this->relatedAccounts($user, $isSuperAdmin)['items'] ?? []) : [],
            'open_flags' => $flags->map(fn (UserActivityPatrolFlag $f) => [
                'id' => $f->id,
                'anomaly_type' => $f->anomaly_type,
                'anomaly_label' => UserActivityAnomalyType::tryFrom($f->anomaly_type)?->label(),
                'risk_level' => $f->risk_level,
                'status' => $f->status,
                'summary' => $f->summary,
                'detected_at' => $f->detected_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UserActivityPatrolFlag>  $allFlags
     * @return array<string, mixed>
     */
    private function anomalySummary(UserActivityPatrolFlag $flag, $allFlags, bool $isSuperAdmin = false): array
    {
        $type = UserActivityAnomalyType::tryFrom($flag->anomaly_type);
        $level = UserActivityRiskLevel::tryFrom($flag->risk_level) ?? UserActivityRiskLevel::Medium;
        $meta = $flag->meta ?? [];

        $details = $this->formatAnomalyMetaDetails($meta, $isSuperAdmin, $flag);
        if ($flag->summary) {
            array_unshift($details, $flag->summary);
        }

        $assessments = match ($type) {
            UserActivityAnomalyType::DisputeSpike => [
                'Possible fraud (intentional bad work to claim refunds)',
                'Possible account compromise',
                'Possible client dissatisfaction (legitimate disputes)',
            ],
            UserActivityAnomalyType::VerificationFail => [
                'Low fraud risk if variance is minor — likely nickname or typo',
                'Request clarification from user if variance exceeds 20%',
            ],
            UserActivityAnomalyType::OffPlatformPayment => [
                'Policy violation — payments must stay on-platform',
                'May indicate attempt to bypass escrow fees or commit fraud',
            ],
            UserActivityAnomalyType::EscrowRoundTripping => [
                'Strong money-laundering signal — escrow funded then released with no deliverables or real conversation',
                'Check whether payee accounts share IP / KYC documents with this client (self-funded payouts)',
                'Hold further payouts and escalate to financial review before clearing',
            ],
            UserActivityAnomalyType::SharedKycDocument => [
                'Same identity document is attached to multiple accounts — likely duplicate / synthetic accounts',
                'Investigate linked accounts for collusion, escrow round-tripping, or ban evasion',
            ],
            UserActivityAnomalyType::SharedIpAccounts => [
                'Multiple accounts operate from the same network — possible multi-accounting or collusion',
                'Cross-reference with shared KYC documents and escrow transaction patterns',
            ],
            default => ['Review activity context and related flags before taking action'],
        };

        return [
            'primary_anomaly' => $type?->label() ?? $flag->anomaly_type,
            'severity' => $level->label(),
            'risk_level' => $level->value,
            'detected_at' => $flag->detected_at?->toIso8601String(),
            'status' => $flag->status,
            'status_label' => UserActivityPatrolStatus::tryFrom($flag->status)?->label(),
            'details' => $details,
            'meta' => $meta,
            'assessments' => $assessments,
            'other_flags_count' => max(0, $allFlags->count() - 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verificationSection(User $user, bool $isSuperAdmin): array
    {
        $documentRoute = $isSuperAdmin ? 'admin.user-verifications.document' : null;

        $verifications = UserVerification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        $items = $verifications->map(function (UserVerification $v) use ($isSuperAdmin, $documentRoute) {
            $payload = $this->verificationPresentation->forReview(
                $v,
                $documentRoute ?? 'operations.api.verifications.document',
            );

            return [
                'id' => $v->id,
                'type' => $v->verification_type ?: ($payload['category'] ?? 'unknown'),
                'label' => $payload['verification_type_label'] ?? Str::headline($v->verification_type ?? 'verification'),
                'status' => $payload['status'] ?? (string) $v->status,
                'status_label' => $payload['status_label'] ?? $payload['status'],
                'fields' => $payload['fields'] ?? [],
                'documents' => $isSuperAdmin ? ($payload['documents'] ?? []) : [],
                'can_view_document' => $isSuperAdmin,
                'submitted_at' => $payload['submitted_at'] ?? null,
            ];
        })->values()->all();

        if ($items === [] && $user->email_verified_at) {
            $items[] = [
                'type' => 'email',
                'label' => 'Email',
                'status' => 'approved',
                'status_label' => 'Approved',
                'documents' => [],
                'can_view_document' => false,
            ];
        }

        return [
            'items' => $items,
            'all_valid' => collect($items)->every(fn ($i) => ($i['status'] ?? '') === 'approved'),
            'staff_sees_documents' => $isSuperAdmin,
        ];
    }

    /**
     * @param  array<string, mixed>  $disputesPayload
     * @return array<string, mixed>
     */
    private function disputesSection(User $user, array $disputesPayload): array
    {
        $active = QuestDispute::query()
            ->where(function ($q) use ($user): void {
                $q->where('opened_by_user_id', $user->id)
                    ->orWhereHas('quest', fn ($qq) => $qq->where('freelancer_id', $user->id)->orWhere('client_id', $user->id));
            })
            ->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn])
            ->with(['quest:id,title', 'openedBy:id,username'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'active_count' => $active->count(),
            'items' => $active->map(fn (QuestDispute $d) => [
                'id' => $d->uuid,
                'quest_title' => $d->quest?->title ?? '—',
                'filed_by' => $d->openedBy?->username ?? '—',
                'amount_display' => NgnMoney::format((int) $d->disputed_amount_minor),
                'status' => $d->status?->value ?? (string) $d->status,
                'created_at' => $d->created_at?->toIso8601String(),
                'resolution_outcome' => $d->resolution_outcome,
            ])->values()->all(),
            'historical' => collect($disputesPayload['tabData'] ?? [])->take(10)->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityTimeline(User $user): array
    {
        $events = collect();

        QuestDispute::query()
            ->where(function ($q) use ($user): void {
                $q->where('opened_by_user_id', $user->id)
                    ->orWhereHas('quest', fn ($qq) => $qq->where('freelancer_id', $user->id));
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->with('quest:id,title')
            ->get()
            ->each(fn ($d) => $events->push([
                'at' => $d->created_at,
                'label' => 'Dispute filed',
                'detail' => $d->quest?->title,
            ]));

        QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->with('quest:id,title,budget_amount_minor')
            ->latest()
            ->limit(20)
            ->get()
            ->each(fn ($o) => $events->push([
                'at' => $o->created_at,
                'label' => 'Proposed on quest',
                'detail' => ($o->quest?->title ?? 'Quest').' — '.NgnMoney::format((int) ($o->quest?->budget_amount_minor ?? 0)),
            ]));

        Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(30))
            ->get()
            ->each(fn ($q) => $events->push([
                'at' => $q->completed_at,
                'label' => 'Job completed',
                'detail' => $q->title,
            ]));

        return $events->sortByDesc('at')->take(25)->values()->map(fn ($e) => [
            'at' => $e['at']?->toIso8601String(),
            'label' => $e['label'],
            'detail' => $e['detail'],
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionsSection(User $user, bool $isSuperAdmin): array
    {
        $since = now()->subDays(30);

        $income = Quest::query()
            ->where('freelancer_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->where('paid_out_minor', '>', 0)
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get()
            ->map(fn ($q) => [
                'date' => $q->completed_at?->toDateString(),
                'label' => 'Job Complete: '.$q->title,
                'amount_display' => NgnMoney::format((int) $q->paid_out_minor),
                'status' => 'Released',
            ])->values()->all();

        $escrows = PaymentEscrow::query()
            ->where(function ($q) use ($user): void {
                $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
            })
            ->where('funded_at', '>=', $since)
            ->with('quest:id,title')
            ->orderByDesc('funded_at')
            ->limit(15)
            ->get();

        $refunds = $escrows->filter(fn ($e) => (int) $e->refunded_minor > 0)->map(fn ($e) => [
            'date' => $e->refunded_at?->toDateString(),
            'label' => $e->quest?->title ?? 'Escrow '.$e->reference,
            'amount_display' => NgnMoney::format((int) $e->refunded_minor),
            'status' => 'Refunded',
        ])->values()->all();

        $chargebacks = $escrows->filter(fn ($e) => (bool) data_get($e->meta, 'chargeback'))->count();
        $refundRate = $escrows->count() > 0
            ? round(($escrows->where('refunded_minor', '>', 0)->count() / $escrows->count()) * 100, 1)
            : 0;

        return [
            'income' => $income,
            'refunds' => $refunds,
            'payment_method' => $isSuperAdmin ? 'Verified bank account on file' : 'Bank account (•••• ****)',
            'financial_red_flags' => $chargebacks > 0 || $refundRate >= 30,
            'refund_rate_percent' => $refundRate,
            'chargeback_count' => $chargebacks,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reversibleTransactions(User $user): array
    {
        return PaymentEscrow::query()
            ->where(function ($q) use ($user): void {
                $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
            })
            ->whereIn('status', ['funded', 'held', 'partially_released'])
            ->where('funded_at', '>=', now()->subDays(90))
            ->with('quest:id,title')
            ->orderByDesc('funded_at')
            ->limit(25)
            ->get()
            ->map(fn (PaymentEscrow $e) => [
                'id' => $e->id,
                'reference' => $e->reference,
                'quest_title' => $e->quest?->title ?? '—',
                'amount_display' => NgnMoney::format((int) $e->amount_minor),
                'refundable_display' => NgnMoney::format($e->releasableMinor()),
                'funded_at' => $e->funded_at?->toIso8601String(),
                'status' => $e->status,
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reviewSignals(User $user): array
    {
        $signals = ReviewAuthenticitySignal::query()
            ->whereHas('review', fn ($q) => $q->where('reviewer_id', $user->id)->orWhere('reviewee_id', $user->id))
            ->where('created_at', '>=', now()->subDays(45))
            ->with('review:id,rating,quest_id,reviewer_id,reviewee_id')
            ->latest()
            ->limit(10)
            ->get();

        $mismatches = Review::query()
            ->where(function ($q) use ($user): void {
                $q->where('reviewer_id', $user->id)->orWhere('reviewee_id', $user->id);
            })
            ->where('created_at', '>=', now()->subDays(45))
            ->whereNotNull('sentiment_score')
            ->where(function ($q): void {
                $q->where(function ($qq): void {
                    $qq->where('rating', '<=', 2)->where('sentiment_score', '>', 0.55);
                })->orWhere(function ($qq): void {
                    $qq->where('rating', '>=', 4)->where('sentiment_score', '<', 0.25);
                });
            })
            ->limit(5)
            ->get();

        $out = $signals->map(fn ($s) => [
            'type' => $s->signal_type,
            'label' => $s->label,
            'confidence' => $s->confidence,
            'review_id' => $s->review_id,
            'rating' => $s->review?->rating,
        ])->values()->all();

        foreach ($mismatches as $review) {
            $out[] = [
                'type' => 'sentiment_mismatch',
                'label' => 'Sentiment mismatch on '.$review->rating.'-star review',
                'confidence' => 0.75,
                'review_id' => $review->id,
                'rating' => $review->rating,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function relatedAccounts(User $user, bool $isSuperAdmin): array
    {
        $latestLogin = LoginEvent::query()->where('user_id', $user->id)->latest('logged_in_at')->first();
        if (! $latestLogin?->ip_address) {
            return ['items' => [], 'isolated' => true, 'message' => 'No login IP data available.'];
        }

        $relatedIds = LoginEvent::query()
            ->where('ip_address', $latestLogin->ip_address)
            ->where('user_id', '!=', $user->id)
            ->where('logged_in_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('user_id');

        if ($relatedIds->isEmpty()) {
            return ['items' => [], 'isolated' => true, 'message' => 'No other accounts share IP/device with this user.'];
        }

        $ipDisplay = $isSuperAdmin ? $latestLogin->ip_address : $this->maskIp($latestLogin->ip_address);

        $related = User::query()->whereIn('id', $relatedIds)->limit(10)->get()->map(fn (User $u) => [
            'id' => $u->id,
            'username' => $u->username,
            'shared_ip' => $ipDisplay,
        ])->values()->all();

        return [
            'items' => $related,
            'isolated' => false,
            'message' => count($related).' related account(s) on same IP.',
            'shared_ip' => $ipDisplay,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private function formatAnomalyMetaDetails(array $meta, bool $isSuperAdmin, UserActivityPatrolFlag $flag): array
    {
        $ipAddress = $meta['ip_address'] ?? null;
        if (! $ipAddress && $isSuperAdmin && $flag->anomaly_type === UserActivityAnomalyType::SharedIpAccounts->value) {
            $ipAddress = LoginEvent::query()
                ->where('user_id', $flag->user_id)
                ->latest('logged_in_at')
                ->value('ip_address');
        }

        $details = [];
        foreach ($meta as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            if ($key === 'ip_address') {
                continue;
            }

            if ($key === 'ip_masked') {
                $details[] = 'IP address: '.($isSuperAdmin && $ipAddress ? $ipAddress : (string) $value);

                continue;
            }

            $details[] = Str::headline((string) $key).': '.$value;
        }

        return $details;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conversationFlags(User $user): array
    {
        $questIds = Quest::query()
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->pluck('id');

        return ConversationThreadReview::query()
            ->whereIn('quest_id', $questIds)
            ->where('last_flagged_at', '>=', now()->subDays(45))
            ->with('quest:id,title')
            ->orderByDesc('last_flagged_at')
            ->limit(5)
            ->get()
            ->map(function (ConversationThreadReview $review) {
                $snippet = ConversationMessageFlag::query()
                    ->where('quest_conversation_thread_id', $review->quest_conversation_thread_id)
                    ->latest()
                    ->value('snippet');

                return [
                    'id' => $review->id,
                    'quest_title' => $review->quest?->title,
                    'flagged_at' => $review->last_flagged_at?->toIso8601String(),
                    'categories' => $review->trigger_categories ?? [],
                    'snippet' => Str::limit((string) $snippet, 120),
                    'status' => $review->status,
                ];
            })->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UserActivityPatrolFlag>  $flags
     * @return list<array<string, mixed>>
     */
    private function moderationHistory(User $user, $flags): array
    {
        $history = collect();

        foreach ($flags as $flag) {
            $history->push([
                'at' => $flag->detected_at?->toIso8601String(),
                'label' => 'Flagged by System',
                'body' => ($flag->summary ?? '').' — Risk: '.Str::upper($flag->risk_level),
                'actor' => 'System',
            ]);
        }

        UserActivityPatrolAction::query()
            ->where('user_id', $user->id)
            ->with('actor:id,name')
            ->orderByDesc('occurred_at')
            ->limit(20)
            ->get()
            ->each(fn ($a) => $history->push([
                'at' => $a->occurred_at?->toIso8601String(),
                'label' => Str::headline($a->action_type),
                'body' => $a->reason_notes,
                'actor' => $a->actor?->name ?? 'Staff',
            ]));

        UserActivityPatrolNote::query()
            ->where('user_id', $user->id)
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->each(fn ($n) => $history->push([
                'at' => $n->created_at?->toIso8601String(),
                'label' => 'Internal note',
                'body' => $n->body,
                'actor' => $n->author?->name,
            ]));

        ActivityLog::query()
            ->where('subject_user_id', $user->id)
            ->whereIn('type', ['operations.user.warning', 'operations.user.suspension', 'operations.user.message_sent'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->each(fn ($l) => $history->push([
                'at' => $l->created_at?->toIso8601String(),
                'label' => $l->title,
                'body' => $l->body,
                'actor' => 'Staff',
            ]));

        return $history->sortByDesc('at')->take(30)->values()->all();
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '—';
        }
        [$local, $domain] = explode('@', $email, 2);

        return Str::substr($local, 0, 2).'***@'.$domain;
    }

    private function maskIp(string $ip): string
    {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0].'.'.$parts[1].'.xx.xxx';
        }

        return Str::limit($ip, 8, '…');
    }
}
