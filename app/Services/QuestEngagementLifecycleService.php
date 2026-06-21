<?php

namespace App\Services;

use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestLifecycleEmailLog;
use App\Models\User;
use App\Notifications\QuestAutoCompletedNotification;
use App\Notifications\QuestLifecycleEngagementNotification;
use App\Services\Quest\QuestCompletionScheduleService;
use App\Support\EscrowAutoReleasePolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QuestEngagementLifecycleService
{
    public function run(): array
    {
        $emails = 0;
        $autoCompleted = 0;

        Quest::query()
            ->where('status', QuestStatus::InProgress)
            ->where('escrow_status', 'funded')
            ->whereNotNull('freelancer_id')
            ->with(['client', 'freelancer', 'acceptedOffer', 'disputes'])
            ->chunkById(75, function ($quests) use (&$emails, &$autoCompleted): void {
                foreach ($quests as $quest) {
                    $emails += $this->processEngagementEmails($quest);
                    if ($this->maybeAutoComplete($quest)) {
                        $autoCompleted++;
                    }
                }
            });

        return ['emails_sent' => $emails, 'auto_completed' => $autoCompleted];
    }

    protected function processEngagementEmails(Quest $quest): int
    {
        $sent = 0;
        $client = $quest->client;
        $freelancer = $quest->freelancer;
        if (! $client || ! $freelancer) {
            return 0;
        }

        $fundedAt = $quest->escrow_funded_at;
        if ($fundedAt === null) {
            return 0;
        }

        $due = $this->expectedCompletionAt($quest);
        if ($due === null) {
            return 0;
        }

        if ($due->lte($fundedAt)) {
            return 0;
        }

        $now = now();

        // Mid-engagement check-in (once each party), only before the due anchor.
        if ($now->lt($due)) {
            $midpoint = $fundedAt->copy()->addSeconds((int) floor($fundedAt->diffInSeconds($due) / 2));
            if ($now->gte($midpoint)) {
                $sent += $this->sendLifecycleEmail(
                    $quest,
                    $freelancer,
                    'mid_checkin_freelancer',
                    __('Quick check-in on “:title”', ['title' => $quest->title]),
                    [
                        __('We hope the quest is going smoothly.'),
                        __('Submit your deliverable from the proposal page when work is ready.'),
                        __('If you hit a blocker, message the client early — you can still open a dispute from the quest if something goes wrong.'),
                    ],
                    route('quests.messages.show', [$quest->getRouteKey()], absolute: true),
                    __('Open quest thread'),
                    route('disputes.index', absolute: true),
                );

                $sent += $this->sendLifecycleEmail(
                    $quest,
                    $client,
                    'mid_checkin_client',
                    __('How is “:title” going?', ['title' => $quest->title]),
                    [
                        __('This is a friendly pulse check while escrow is active.'),
                        __('You will review and approve deliverables after the freelancer submits work.'),
                        __('If something is off, open a dispute early — waiting usually makes resolution harder.'),
                    ],
                    route('quests.proposals.show', [$quest, $quest->acceptedOffer], absolute: true),
                    __('Open proposal'),
                    route('quests.disputes.create', [$quest->getRouteKey()], absolute: true),
                );
            }

            return $sent;
        }

        if ($quest->completed_at !== null || $this->hasBlockingDispute($quest)) {
            return $sent;
        }

        $contract = \App\Models\QuestContract::query()
            ->where('quest_id', $quest->id)
            ->where('quest_offer_id', $quest->accepted_quest_offer_id)
            ->first();
        if ($contract !== null && ($contract->pending_extension_id !== null || $contract->deadline_clock_paused_at !== null)) {
            return $sent;
        }

        if ($quest->delivered_at === null) {
            return $sent + $this->processEngagementEmailsLegacyAfterDue($quest);
        }

        return $sent + $this->processReviewWindowEmails($quest);
    }

    protected function processReviewWindowEmails(Quest $quest): int
    {
        $sent = 0;
        $client = $quest->client;
        if (! $client || $quest->delivered_at === null) {
            return 0;
        }

        $now = now();
        $reviewDeadline = $quest->delivery_review_deadline_at
            ?? EscrowAutoReleasePolicy::releaseAt($quest->delivered_at);

        if ($now->gte($reviewDeadline)) {
            return 0;
        }

        $releaseHours = EscrowAutoReleasePolicy::releaseHours();
        $questUrl = route('quests.proposals.show', [$quest, $quest->acceptedOffer], absolute: true);
        $disputeUrl = route('quests.disputes.create', [$quest->getRouteKey()], absolute: true);

        if ($now->gte($quest->delivered_at)) {
            $sent += $this->sendLifecycleEmail(
                $quest,
                $client,
                'client_review_due_on_submit',
                __('Deliverable submitted — review “:title”', ['title' => $quest->title]),
                [
                    __('The freelancer submitted work for your review.'),
                    __('Approve if it meets the brief, request revisions, or open a dispute if something is wrong.'),
                    __('If you take no action and no dispute is open, escrow may auto-release :hours hours after submission.', [
                        'hours' => $releaseHours,
                    ]),
                ],
                $questUrl,
                __('Review deliverable'),
                $disputeUrl,
            );
        }

        if ($now->gte($quest->delivered_at->copy()->addHours(24))) {
            $remaining = (int) max(0, $now->diffInHours($reviewDeadline, false));
            $sent += $this->sendLifecycleEmail(
                $quest,
                $client,
                'client_review_24h_after_submit',
                __('Reminder: review deliverable — “:title”', ['title' => $quest->title]),
                [
                    __('It has been 24 hours since the freelancer submitted work.'),
                    __('Approve, request revisions, or open a dispute.'),
                    __('Auto-release may occur in about :hours hours if no dispute is open.', ['hours' => $remaining]),
                ],
                $questUrl,
                __('Review now'),
                $disputeUrl,
            );
        }

        if ($now->gte($quest->delivered_at->copy()->addHours(48))) {
            $remaining = (int) max(0, $now->diffInHours($reviewDeadline, false));
            $sent += $this->sendLifecycleEmail(
                $quest,
                $client,
                'client_review_48h_after_submit',
                __('Final review reminder — “:title”', ['title' => $quest->title]),
                [
                    __('This is your final reminder before the automatic review window closes.'),
                    __('Approve now if satisfied, or open a dispute immediately.'),
                    __('Auto-release may occur in about :hours hours.', ['hours' => $remaining]),
                ],
                $questUrl,
                __('Review now'),
                $disputeUrl,
            );
        }

        return $sent;
    }

    protected function processEngagementEmailsLegacyAfterDue(Quest $quest): int
    {
        $sent = 0;
        $client = $quest->client;
        if (! $client || $quest->delivered_at !== null) {
            return 0;
        }

        $due = $this->expectedCompletionAt($quest);
        if ($due === null || now()->lt($due)) {
            return 0;
        }

        $now = now();
        $releaseHours = EscrowAutoReleasePolicy::releaseHours();
        $questUrl = route('quests.show', [$quest->getRouteKey()], absolute: true);
        $disputeUrl = route('quests.disputes.create', [$quest->getRouteKey()], absolute: true);

        if ($now->gte($due)) {
            $sent += $this->sendLifecycleEmail(
                $quest,
                $client,
                'client_work_deadline_passed',
                __('Work deadline passed — “:title”', ['title' => $quest->title]),
                [
                    __('The agreed delivery date has passed and no deliverable has been submitted yet.'),
                    __('Message the freelancer for a status update or open a dispute if needed.'),
                ],
                $questUrl,
                __('Open quest'),
                $disputeUrl,
            );
        }

        return $sent;
    }

    protected function sendLifecycleEmail(
        Quest $quest,
        User $recipient,
        string $emailKey,
        string $subject,
        array $bodyLines,
        string $primaryUrl,
        string $primaryLabel,
        string $secondaryUrl,
    ): int {
        if (QuestLifecycleEmailLog::query()
            ->where('quest_id', $quest->id)
            ->where('email_key', $emailKey)
            ->where('recipient_user_id', $recipient->id)
            ->exists()) {
            return 0;
        }

        DB::transaction(function () use ($quest, $recipient, $emailKey, $subject, $bodyLines, $primaryUrl, $primaryLabel, $secondaryUrl): void {
            $recipient->notify(new QuestLifecycleEngagementNotification(
                quest: $quest,
                emailKey: $emailKey,
                subjectLine: $subject,
                bodyLines: $bodyLines,
                primaryUrl: $primaryUrl,
                primaryLabel: $primaryLabel,
                secondaryUrl: $secondaryUrl,
            ));

            QuestLifecycleEmailLog::query()->create([
                'quest_id' => $quest->id,
                'email_key' => $emailKey,
                'recipient_user_id' => $recipient->id,
                'sent_at' => now(),
            ]);
        });

        return 1;
    }

    public function expectedCompletionAt(Quest $quest): ?Carbon
    {
        return app(QuestCompletionScheduleService::class)->engagementAnchorAt($quest);
    }

    protected function hasBlockingDispute(Quest $quest): bool
    {
        $blocking = [
            QuestDisputeStatus::Open,
            QuestDisputeStatus::SelfResolving,
            QuestDisputeStatus::Escalated,
            QuestDisputeStatus::AwaitingRuling,
        ];

        return $quest->disputes()
            ->whereIn('status', $blocking)
            ->exists();
    }

    protected function maybeAutoComplete(Quest $quest): bool
    {
        if ($quest->status !== QuestStatus::InProgress) {
            return false;
        }

        if ($quest->escrow_status !== 'funded' && $quest->escrow_status !== 'partially_released') {
            return false;
        }

        if ($quest->completed_at !== null || $quest->auto_completed_at !== null) {
            return false;
        }

        if ($quest->delivered_at === null) {
            return false;
        }

        $reviewDeadline = $quest->delivery_review_deadline_at
            ?? EscrowAutoReleasePolicy::releaseAt($quest->delivered_at);

        if (now()->lt($reviewDeadline)) {
            return false;
        }

        if ($this->hasBlockingDispute($quest)) {
            return false;
        }

        $contract = \App\Models\QuestContract::query()
            ->where('quest_id', $quest->id)
            ->where('quest_offer_id', $quest->accepted_quest_offer_id)
            ->first();
        if ($contract !== null && ($contract->pending_extension_id !== null || $contract->deadline_clock_paused_at !== null)) {
            return false;
        }

        if ($quest->status === QuestStatus::InDispute) {
            return false;
        }

        $logger = app(\App\Services\QuestCompletionEventLogger::class);
        $quest->loadMissing('acceptedOffer', 'client', 'freelancer');

        if ($quest->delivery_acknowledged_at === null) {
            $recurring = app(\App\Services\Quest\QuestRecurringEngagementService::class);
            if ($recurring->isRecurring($quest)) {
                $installment = $recurring->currentInstallment($quest);
                if ($installment !== null) {
                    $recurring->markInstallmentAutoApproved($quest, $installment);
                }
            } else {
                $quest->update([
                    'delivery_acknowledged_at' => now(),
                    'delivery_acknowledged_by' => null,
                ]);
            }
            $quest->refresh();
            $logger->record($quest, 'auto_delivery_acknowledged', null, null, [
                'reason' => 'auto_release_after_review_window',
                'hours' => EscrowAutoReleasePolicy::releaseHours(),
            ]);
        }

        if (! \App\Support\EscrowReleasePolicy::canReleaseFunds($quest)) {
            $logger->record($quest, 'auto_release_blocked', null, null, [
                'reason' => \App\Support\EscrowReleasePolicy::blockedReleaseReason($quest),
            ]);

            return false;
        }

        $releaseHours = EscrowAutoReleasePolicy::releaseHours();
        $recurring = app(\App\Services\Quest\QuestRecurringEngagementService::class);

        try {
            $result = $recurring->processApprovedRelease(
                $quest->fresh(),
                null,
                __('Auto-released :hours hours after deliverable submission review window', ['hours' => $releaseHours]),
                'auto_release_review_window',
            );
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        if (! $result['quest_completed']) {
            $logger->record($quest->fresh(), 'installment_auto_released', null, null, [
                'installment_number' => $result['installment_number'],
                'amount_minor' => $result['amount_minor'],
                'closure_type' => 'auto_installment_release',
            ]);

            return true;
        }

        $payoutMinor = (int) ($quest->acceptedOffer?->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0);

        $quest->update([
            'status' => QuestStatus::Completed,
            'completed_at' => now(),
            'funds_released_at' => now(),
            'completed_on_time' => false,
            'auto_completed_at' => now(),
            'closure_type' => 'auto_completed_silent_72h',
            'paid_out_minor' => $payoutMinor > 0 ? $payoutMinor : $quest->paid_out_minor,
        ]);

        $quest->refresh();
        $logger->record($quest, 'auto_funds_released', null, null, [
            'closure_type' => 'auto_completed_silent_72h',
            'hours_after_due' => $releaseHours,
        ]);

        foreach (array_filter([$quest->client, $quest->freelancer]) as $party) {
            $party?->notify(new QuestAutoCompletedNotification($quest));
        }

        app(\App\Services\Quest\QuestJourneySurveyService::class)->onQuestFundsReleased($quest->fresh());

        $contract = \App\Models\QuestContract::query()
            ->where('quest_id', $quest->id)
            ->where('quest_offer_id', $quest->accepted_quest_offer_id)
            ->first();
        if ($contract !== null) {
            app(\App\Services\Contracts\ContractLifecycleService::class)->markCompleted($contract);
        }

        return true;
    }
}
