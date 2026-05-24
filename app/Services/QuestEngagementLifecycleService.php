<?php

namespace App\Services;

use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestLifecycleEmailLog;
use App\Models\User;
use App\Notifications\QuestAutoCompletedNotification;
use App\Notifications\QuestLifecycleEngagementNotification;
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
                        __('When deliverables meet the brief, mark the job complete from the quest page so funds can follow your approval timeline.'),
                        __('If something is off, open a dispute early — waiting usually makes resolution harder.'),
                    ],
                    route('quests.show', [$quest->getRouteKey()], absolute: true),
                    __('Open quest'),
                    route('quests.disputes.create', [$quest->getRouteKey()], absolute: true),
                );
            }

            return $sent;
        }

        // Post–expected completion cadence: +24h, +48h, +72h (catch-up friendly).
        $t24 = $due->copy()->addHours(24);
        $t48 = $due->copy()->addHours(48);
        $t72 = $due->copy()->addHours(72);

        if ($now->gte($t24)) {
            $sent += $this->postDuePair(
                $quest,
                $client,
                $freelancer,
                'post_due_24h',
                __('Past due date — quick update on “:title”', ['title' => $quest->title]),
                [
                    __('The agreed completion anchor has passed. If work is still underway, align in the thread on what is left and when it will land.'),
                    __('If timelines need to change, agree there in writing — extending the plan is fine when both sides are clear.'),
                    __('If you are satisfied, the client should mark complete; if not, use the dispute process rather than going silent.'),
                ],
            );
        }

        if ($now->gte($t48)) {
            $sent += $this->postDuePair(
                $quest,
                $client,
                $freelancer,
                'post_due_48h',
                __('Reminder: “:title” is awaiting closure', ['title' => $quest->title]),
                [
                    __('We still show this quest as in progress after the agreed date.'),
                    __('Clients: if delivery looks good, please mark complete. Freelancers: nudge politely in-thread if you are waiting on confirmation.'),
                    __('If the job is genuinely late, agree a revised finish date in the thread so expectations stay fair for both sides.'),
                    __('HustleSafe may auto-complete and release escrow 72 hours after the agreed date if there is no dispute and we do not hear otherwise — see Terms for detail.'),
                ],
            );
        }

        if ($now->gte($t72)) {
            $sent += $this->postDuePair(
                $quest,
                $client,
                $freelancer,
                'post_due_72h_final',
                __('Final notice before auto-completion — “:title”', ['title' => $quest->title]),
                [
                    __('This is the last scheduled notice before the silent-completion window closes.'),
                    __('If you need help, open or update a dispute now with concrete facts — silence after this window is treated as acceptance of completion under our Terms.'),
                    __('When no dispute is open, the quest may be marked complete and escrow released to the freelancer shortly after this email.'),
                ],
            );
        }

        return $sent;
    }

    /**
     * @return array<int, string>
     */
    protected function postDuePair(
        Quest $quest,
        User $client,
        User $freelancer,
        string $prefix,
        string $subject,
        array $lines,
    ): int {
        $cKey = $prefix.'_client';
        $fKey = $prefix.'_freelancer';

        $disputesUrl = route('disputes.index', absolute: true);
        $questUrl = route('quests.show', [$quest->getRouteKey()], absolute: true);

        $n = 0;
        $n += $this->sendLifecycleEmail(
            $quest,
            $freelancer,
            $fKey,
            $subject,
            $lines,
            route('quests.messages.show', [$quest->getRouteKey()], absolute: true),
            __('Open quest thread'),
            $disputesUrl,
        );
        $n += $this->sendLifecycleEmail(
            $quest,
            $client,
            $cKey,
            $subject,
            $lines,
            $questUrl,
            __('Open quest'),
            route('quests.disputes.create', [$quest->getRouteKey()], absolute: true),
        );

        return $n;
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

        if (now()->lt($due->copy()->addHours(72))) {
            return false;
        }

        if ($this->hasBlockingDispute($quest)) {
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
                'reason' => '72h_post_due_window',
            ]);
        }

        if (! \App\Support\EscrowReleasePolicy::canReleaseFunds($quest)) {
            $logger->record($quest, 'auto_release_blocked', null, null, [
                'reason' => \App\Support\EscrowReleasePolicy::blockedReleaseReason($quest),
            ]);

            return false;
        }

        try {
            app(\App\Services\Payments\EscrowPaymentService::class)
                ->releaseEscrowToWallet($quest, null, __('Auto-released after 72-hour review window'));
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
        ]);

        foreach (array_filter([$quest->client, $quest->freelancer]) as $party) {
            $party?->notify(new QuestAutoCompletedNotification($quest));
        }

        return true;
    }
}
