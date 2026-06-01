<?php

namespace App\Services;

use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestLifecycleEmailLog;
use App\Models\User;
use App\Notifications\QuestAutoCompletedNotification;
use App\Notifications\QuestLifecycleEngagementNotification;
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
                        __('If anything is unclear, use the quest thread so everything stays documented on-platform.'),
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
                        __('When deliverables meet the brief, mark the job complete from the quest page.'),
                        __('If something is off, open a dispute early — waiting usually makes resolution harder.'),
                    ],
                    route('quests.show', [$quest->getRouteKey()], absolute: true),
                    __('Open quest'),
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

        $releaseHours = EscrowAutoReleasePolicy::releaseHours();
        $questUrl = route('quests.show', [$quest->getRouteKey()], absolute: true);
        $disputeUrl = route('quests.disputes.create', [$quest->getRouteKey()], absolute: true);

        if ($now->gte($due)) {
            $sent += $this->sendLifecycleEmail(
                $quest,
                $client,
                'client_auto_release_due_day',
                __('Agreed delivery date — review “:title”', ['title' => $quest->title]),
                [
                    __('Today is the agreed delivery date for this quest.'),
                    __('If the work meets the brief, mark the job complete. If something is wrong, message the freelancer or open a dispute.'),
                    __('If you take no action, escrow may auto-release to the freelancer :hours hours after the agreed delivery date.', [
                        'hours' => $releaseHours,
                    ]),
                ],
                $questUrl,
                __('Review quest'),
                $disputeUrl,
            );
        }

        if ($now->gte($due->copy()->addHours(24))) {
            $remaining = EscrowAutoReleasePolicy::hoursRemaining($due, $now);
            $sent += $this->sendLifecycleEmail(
                $quest,
                $client,
                'client_auto_release_24h',
                __('Reminder: review “:title”', ['title' => $quest->title]),
                [
                    __('It has been 24 hours since the agreed delivery date and this quest is still open.'),
                    __('Mark the job complete if you are satisfied, or open a dispute if something is not right.'),
                    __('Escrow may auto-release in about :hours hours if no dispute is opened.', ['hours' => $remaining]),
                ],
                $questUrl,
                __('Mark complete or dispute'),
                $disputeUrl,
            );
        }

        if ($now->gte($due->copy()->addHours(36))) {
            $remaining = EscrowAutoReleasePolicy::hoursRemaining($due, $now);
            $sent += $this->sendLifecycleEmail(
                $quest,
                $client,
                'client_auto_release_36h_final',
                __('Final reminder before auto-release — “:title”', ['title' => $quest->title]),
                [
                    __('This is your final scheduled reminder before the automatic escrow release window closes.'),
                    __('Mark complete now if the deliverables meet the brief, or open a dispute immediately with clear facts.'),
                    __('If no dispute is open, escrow may release in about :hours hours.', ['hours' => $remaining]),
                ],
                $questUrl,
                __('Review now'),
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
        if ($quest->due_at) {
            return $quest->due_at->copy()->timezone(config('app.timezone'));
        }

        if ($quest->estimated_delivery_date) {
            return Carbon::parse($quest->estimated_delivery_date, config('app.timezone'))->endOfDay();
        }

        $quest->loadMissing('acceptedOffer');
        if ($quest->acceptedOffer?->planned_finish_date) {
            return Carbon::parse($quest->acceptedOffer->planned_finish_date, config('app.timezone'))->endOfDay();
        }

        if ($quest->estimated_completion_days && $quest->escrow_funded_at) {
            return $quest->escrow_funded_at->copy()->addDays((int) $quest->estimated_completion_days);
        }

        return null;
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

        if ($quest->escrow_status !== 'funded') {
            return false;
        }

        if ($quest->completed_at !== null || $quest->auto_completed_at !== null) {
            return false;
        }

        $due = $this->expectedCompletionAt($quest);
        if ($due === null) {
            return false;
        }

        if (now()->lt(EscrowAutoReleasePolicy::releaseAt($due))) {
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
            $quest->update([
                'delivery_acknowledged_at' => now(),
                'delivery_acknowledged_by' => null,
            ]);
            $quest->refresh();
            $logger->record($quest, 'auto_delivery_acknowledged', null, null, [
                'reason' => 'auto_release_after_agreed_delivery',
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

        try {
            app(\App\Services\Payments\EscrowPaymentService::class)
                ->releaseEscrowToWallet($quest, null, __('Auto-released :hours hours after agreed delivery date', ['hours' => $releaseHours]));
        } catch (\Throwable $e) {
            report($e);

            return false;
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
