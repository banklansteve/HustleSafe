<?php

namespace App\Services\Operations;

use App\Enums\DisputeMessageKind;
use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Enums\UserVerificationStatus;
use App\Models\ConversationMessageFlag;
use App\Models\ConversationThreadReview;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\Review;
use App\Models\StaffProactiveOutreachItem;
use App\Models\User;
use App\Models\UserTrustMetric;
use App\Models\UserVerification;
use Illuminate\Support\Facades\Schema;

class ProactiveOutreachScannerService
{
    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        if (! Schema::hasTable('staff_proactive_outreach_items')) {
            return [];
        }

        $counts = [
            'freelancer_kyc_no_proposal_14d' => $this->scanFreelancerKycNoProposal(),
            'client_no_quest_21d' => $this->scanClientNoQuest(),
            'awarded_no_escrow_funded' => $this->scanAwardedNoEscrow(),
            'freelancer_rating_drop' => $this->scanFreelancerRatingDrop(),
            'dispute_open_no_evidence' => $this->scanDisputeNoEvidence(),
            'off_platform_payment_flagged' => $this->scanOffPlatformPayment(),
        ];

        $this->autoResolveStale();

        return $counts;
    }

    private function scanFreelancerKycNoProposal(): int
    {
        $days = (int) config('operations.proactive_outreach.freelancer_kyc_no_proposal_days', 14);
        $cutoff = now()->subDays($days);
        $count = 0;

        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->where(function ($q) use ($cutoff): void {
                $q->where('verification_tier', '>=', 2)
                    ->orWhereHas('userVerifications', fn ($v) => $v
                        ->whereIn('status', [UserVerificationStatus::Verified->value, UserVerificationStatus::Approved->value])
                        ->where('reviewed_at', '<=', $cutoff));
            })
            ->whereDoesntHave('questOffers', fn ($q) => $q->whereIn('status', ['submitted', 'shortlisted', 'pending_award', 'accepted', 'rejected', 'withdrawn']))
            ->chunkById(100, function ($users) use (&$count, $days, $cutoff): void {
                foreach ($users as $user) {
                    $kycAt = $this->freelancerKycCompletedAt($user);
                    if (! $kycAt || $kycAt->greaterThan($cutoff)) {
                        continue;
                    }

                    if ($this->upsertItem(
                        'freelancer_kyc_no_proposal_14d',
                        $this->fingerprint('freelancer_kyc_no_proposal_14d', $user->id),
                        [
                            'target_user_id' => $user->id,
                            'context' => [
                                'days_inactive' => (string) $kycAt->diffInDays(now()),
                                'kyc_completed_at' => $kycAt->toIso8601String(),
                                'verification_tier' => $user->verification_tier,
                            ],
                        ],
                    )) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function scanClientNoQuest(): int
    {
        $days = (int) config('operations.proactive_outreach.client_no_quest_days', 21);
        $cutoff = now()->subDays($days);
        $count = 0;

        User::query()
            ->where(function ($q): void {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'client'))
                    ->orWhere('account_type', 'client');
            })
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('questsAsClient', fn ($q) => $q->whereNotIn('status', [QuestStatus::Draft->value]))
            ->chunkById(100, function ($users) use (&$count, $days): void {
                foreach ($users as $user) {
                    if ($this->upsertItem(
                        'client_no_quest_21d',
                        $this->fingerprint('client_no_quest_21d', $user->id),
                        [
                            'target_user_id' => $user->id,
                            'context' => [
                                'days_inactive' => (string) $user->created_at?->diffInDays(now()),
                                'registered_at' => $user->created_at?->toIso8601String(),
                            ],
                        ],
                    )) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function scanAwardedNoEscrow(): int
    {
        $hours = (int) config('operations.proactive_outreach.awarded_no_escrow_hours', 48);
        $cutoff = now()->subHours($hours);
        $count = 0;

        Quest::query()
            ->whereNull('escrow_funded_at')
            ->where(function ($q): void {
                $q->whereNotNull('pending_award_offer_id')
                    ->orWhereNotNull('accepted_quest_offer_id');
            })
            ->with(['client:id,name,email', 'pendingAwardOffer.freelancer:id,name', 'acceptedOffer.freelancer:id,name'])
            ->chunkById(50, function ($quests) use (&$count, $cutoff, $hours): void {
                foreach ($quests as $quest) {
                    $offer = $quest->pendingAwardOffer ?? $quest->acceptedOffer;
                    if (! $offer) {
                        continue;
                    }

                    $awardAt = $offer->award_client_confirmed_at
                        ?? $offer->accepted_at
                        ?? $offer->updated_at;

                    if (! $awardAt || $awardAt->greaterThan($cutoff)) {
                        continue;
                    }

                    $client = $quest->client;
                    if (! $client) {
                        continue;
                    }

                    if ($this->upsertItem(
                        'awarded_no_escrow_funded',
                        $this->fingerprint('awarded_no_escrow_funded', $client->id, $quest->id),
                        [
                            'target_user_id' => $client->id,
                            'quest_id' => $quest->id,
                            'quest_offer_id' => $offer->id,
                            'context' => [
                                'quest_title' => $quest->title,
                                'quest_reference' => $quest->reference_code,
                                'freelancer_name' => $offer->freelancer?->name,
                                'hours_since_award' => (string) $awardAt->diffInHours(now()),
                                'budget_minor' => $quest->budget_amount_minor,
                            ],
                        ],
                        priorityOverride: 'high',
                        scoreOverride: 80 + min(15, (int) ($awardAt->diffInHours(now()) / 24)),
                    )) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function scanFreelancerRatingDrop(): int
    {
        if (! Schema::hasTable('user_trust_metrics')) {
            return 0;
        }

        $windowDays = (int) config('operations.proactive_outreach.rating_drop_window_days', 14);
        $threshold = (float) config('operations.proactive_outreach.rating_drop_threshold', 0.5);
        $count = 0;

        UserTrustMetric::query()
            ->whereNotNull('avg_rating_as_freelancer')
            ->where('ratings_count_as_freelancer', '>=', 3)
            ->with('user:id,name,email')
            ->chunkById(100, function ($metrics) use (&$count, $windowDays, $threshold): void {
                foreach ($metrics as $metric) {
                    $user = $metric->user;
                    if (! $user) {
                        continue;
                    }

                    $recentAvg = Review::query()
                        ->where('reviewee_id', $user->id)
                        ->where('created_at', '>=', now()->subDays($windowDays))
                        ->avg('rating');

                    if ($recentAvg === null) {
                        continue;
                    }

                    $overall = (float) $metric->avg_rating_as_freelancer;
                    $drop = $overall - (float) $recentAvg;

                    if ($drop < $threshold) {
                        continue;
                    }

                    if ($this->upsertItem(
                        'freelancer_rating_drop',
                        $this->fingerprint('freelancer_rating_drop', $user->id),
                        [
                            'target_user_id' => $user->id,
                            'context' => [
                                'rating_before' => number_format($overall, 2),
                                'rating_after' => number_format((float) $recentAvg, 2),
                                'rating_drop' => number_format($drop, 2),
                                'window_days' => (string) $windowDays,
                            ],
                        ],
                        priorityOverride: 'high',
                        scoreOverride: 70 + (int) min(20, $drop * 20),
                    )) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function scanDisputeNoEvidence(): int
    {
        if (! Schema::hasTable('dispute_messages')) {
            return 0;
        }

        $hours = (int) config('operations.proactive_outreach.dispute_no_evidence_hours', 72);
        $cutoff = now()->subHours($hours);
        $count = 0;

        QuestDispute::query()
            ->whereNotIn('status', [QuestDisputeStatus::Resolved->value, QuestDisputeStatus::ClosedWithdrawn->value])
            ->where('created_at', '<=', $cutoff)
            ->with(['openedBy:id,name,email', 'quest:id,title,reference_code'])
            ->chunkById(50, function ($disputes) use (&$count): void {
                foreach ($disputes as $dispute) {
                    $hasEvidence = $dispute->messages()
                        ->where('kind', DisputeMessageKind::Evidence->value)
                        ->exists();

                    if ($hasEvidence) {
                        continue;
                    }

                    $target = $dispute->openedBy;
                    if (! $target) {
                        continue;
                    }

                    if ($this->upsertItem(
                        'dispute_open_no_evidence',
                        $this->fingerprint('dispute_open_no_evidence', $target->id, null, null, $dispute->id),
                        [
                            'target_user_id' => $target->id,
                            'quest_id' => $dispute->quest_id,
                            'quest_dispute_id' => $dispute->id,
                            'context' => [
                                'quest_title' => $dispute->quest?->title,
                                'quest_reference' => $dispute->quest?->reference_code,
                                'dispute_reason' => $dispute->reason,
                                'opened_at' => $dispute->created_at?->toIso8601String(),
                            ],
                        ],
                        priorityOverride: 'high',
                    )) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function scanOffPlatformPayment(): int
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return 0;
        }

        $count = 0;

        ConversationMessageFlag::query()
            ->where('trigger_category', 'off_platform_payment')
            ->where('status', 'pending')
            ->where('flagged_at', '<=', now()->subHours(2))
            ->with('sender:id,name,email')
            ->latest('flagged_at')
            ->limit(200)
            ->get()
            ->each(function (ConversationMessageFlag $flag) use (&$count): void {
                $user = $flag->sender;
                if (! $user) {
                    return;
                }

                $reviewId = ConversationThreadReview::query()
                    ->where('quest_conversation_thread_id', $flag->quest_conversation_thread_id)
                    ->value('id');

                if ($this->upsertItem(
                    'off_platform_payment_flagged',
                    $this->fingerprint('off_platform_payment_flagged', $user->id, $flag->quest_id, $flag->quest_offer_id, null, $reviewId),
                    [
                        'target_user_id' => $user->id,
                        'quest_id' => $flag->quest_id,
                        'quest_offer_id' => $flag->quest_offer_id,
                        'conversation_thread_review_id' => $reviewId,
                        'context' => [
                            'flag_id' => $flag->id,
                            'matched_pattern' => $flag->matched_pattern_redacted,
                            'flagged_at' => $flag->flagged_at?->toIso8601String(),
                        ],
                    ],
                    priorityOverride: 'urgent',
                    scoreOverride: 90,
                )) {
                    $count++;
                }
            });

        return $count;
    }

    private function autoResolveStale(): void
    {
        Quest::query()
            ->whereNotNull('escrow_funded_at')
            ->pluck('id')
            ->each(function (int $questId): void {
                StaffProactiveOutreachItem::query()
                    ->where('situation_key', 'awarded_no_escrow_funded')
                    ->where('quest_id', $questId)
                    ->whereNull('resolved_at')
                    ->update([
                        'status' => 'auto_resolved',
                        'resolved_at' => now(),
                        'resolution_note' => 'Escrow funded.',
                    ]);
            });

        User::query()
            ->whereHas('questOffers', fn ($q) => $q->whereIn('status', ['submitted', 'shortlisted', 'pending_award', 'accepted']))
            ->pluck('id')
            ->each(function (int $userId): void {
                StaffProactiveOutreachItem::query()
                    ->where('situation_key', 'freelancer_kyc_no_proposal_14d')
                    ->where('target_user_id', $userId)
                    ->whereNull('resolved_at')
                    ->update([
                        'status' => 'auto_resolved',
                        'resolved_at' => now(),
                        'resolution_note' => 'User submitted a proposal.',
                    ]);
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertItem(
        string $situationKey,
        string $fingerprint,
        array $payload,
        ?string $priorityOverride = null,
        ?int $scoreOverride = null,
    ): bool {
        $meta = config("operations.proactive_outreach.situations.{$situationKey}", []);
        $existing = StaffProactiveOutreachItem::query()->where('fingerprint', $fingerprint)->first();

        if ($existing && in_array($existing->status, ['resolved', 'auto_resolved'], true)) {
            return false;
        }

        if ($existing && $existing->snoozed_until && $existing->snoozed_until->isFuture()) {
            return false;
        }

        $attributes = [
            'situation_key' => $situationKey,
            'status' => $existing?->status === 'contacted' ? 'contacted' : 'open',
            'priority' => $priorityOverride ?? ($meta['priority'] ?? 'medium'),
            'priority_score' => $scoreOverride ?? ($meta['priority_score'] ?? 50),
            'target_user_id' => $payload['target_user_id'] ?? null,
            'quest_id' => $payload['quest_id'] ?? null,
            'quest_offer_id' => $payload['quest_offer_id'] ?? null,
            'quest_dispute_id' => $payload['quest_dispute_id'] ?? null,
            'conversation_thread_review_id' => $payload['conversation_thread_review_id'] ?? null,
            'context' => $payload['context'] ?? [],
            'suggested_template_slug' => $meta['default_template_slug'] ?? null,
            'detected_at' => $existing?->detected_at ?? now(),
        ];

        if ($existing) {
            $existing->fill($attributes)->save();

            return false;
        }

        StaffProactiveOutreachItem::query()->create([
            ...$attributes,
            'fingerprint' => $fingerprint,
        ]);

        return true;
    }

    private function fingerprint(
        string $situation,
        ?int $userId = null,
        ?int $questId = null,
        ?int $offerId = null,
        ?int $disputeId = null,
        ?int $threadReviewId = null,
    ): string {
        return hash('sha256', implode(':', array_filter([
            $situation,
            $userId,
            $questId,
            $offerId,
            $disputeId,
            $threadReviewId,
        ], fn ($v) => $v !== null && $v !== '')));
    }

    private function freelancerKycCompletedAt(User $user): ?\Illuminate\Support\Carbon
    {
        $latest = UserVerification::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [UserVerificationStatus::Verified->value, UserVerificationStatus::Approved->value])
            ->max('reviewed_at');

        if ($latest) {
            return \Illuminate\Support\Carbon::parse($latest);
        }

        if (($user->verification_tier ?? 0) >= 2 && $user->updated_at) {
            return $user->updated_at;
        }

        return null;
    }
}
