<?php

namespace App\Services\Quest;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestNudgeLog;
use App\Models\User;
use App\Notifications\QuestEngagementNudgeNotification;
use App\Services\QuestEngagementLifecycleService;
use Illuminate\Support\Facades\DB;

class QuestAutoNudgeService
{
    public function __construct(
        private readonly QuestEngagementLifecycleService $lifecycle,
    ) {}

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $sent = [
            'proposals_no_client_login' => 0,
            'awarded_no_escrow' => 0,
            'delivery_pending_client_action' => 0,
        ];

        $this->processProposalsNoClientLogin($sent);
        $this->processAwardedNoEscrow($sent);
        $this->processDeliveryPendingClientAction($sent);

        return $sent;
    }

    /**
     * @param  array<string, int>  $sent
     */
    private function processProposalsNoClientLogin(array &$sent): void
    {
        Quest::query()
            ->where('status', QuestStatus::Open)
            ->where('offers_count', '>', 0)
            ->with('client')
            ->chunkById(50, function ($quests) use (&$sent): void {
                foreach ($quests as $quest) {
                    $client = $quest->client;
                    if (! $client || ! $client->last_active_at) {
                        continue;
                    }

                    if ($client->last_active_at->greaterThan(now()->subDays(5))) {
                        continue;
                    }

                    if ($this->sendNudge(
                        $quest,
                        $client,
                        'proposals_no_client_login',
                        __('Freelancers are waiting on “:title”', ['title' => $quest->title]),
                        [
                            __('Your quest has received proposals, but you have not signed in for a while.'),
                            __('Review proposals and shortlist or respond so good freelancers know you are still hiring.'),
                            __('Staying active helps your quest rank and avoids missed talent.'),
                        ],
                        route('quests.client.proposals.index', $quest, absolute: true),
                        __('Review proposals'),
                    )) {
                        $sent['proposals_no_client_login']++;
                    }
                }
            });
    }

    /**
     * @param  array<string, int>  $sent
     */
    private function processAwardedNoEscrow(array &$sent): void
    {
        Quest::query()
            ->where('status', QuestStatus::Assigned)
            ->whereNull('escrow_funded_at')
            ->whereNotNull('accepted_quest_offer_id')
            ->with(['client', 'freelancer', 'acceptedOffer'])
            ->chunkById(50, function ($quests) use (&$sent): void {
                foreach ($quests as $quest) {
                    $acceptedAt = $quest->acceptedOffer?->accepted_at;
                    if (! $acceptedAt || $acceptedAt->greaterThan(now()->subHours(48))) {
                        continue;
                    }

                    $client = $quest->client;
                    $freelancer = $quest->freelancer;

                    if ($client && $this->sendNudge(
                        $quest,
                        $client,
                        'awarded_no_escrow',
                        __('Fund escrow for “:title”', ['title' => $quest->title]),
                        [
                            __('You accepted a freelancer more than 48 hours ago, but escrow is not funded yet.'),
                            __('Fund escrow so work can begin — your freelancer has been notified too.'),
                        ],
                        route('quests.show', [$quest->getRouteKey()], absolute: true),
                        __('Fund escrow'),
                    )) {
                        $sent['awarded_no_escrow']++;
                    }

                    if ($freelancer && $this->sendNudge(
                        $quest,
                        $freelancer,
                        'awarded_no_escrow_freelancer',
                        __('Waiting on escrow for “:title”', ['title' => $quest->title]),
                        [
                            __('You were awarded this quest, but the client has not funded escrow yet.'),
                            __('We have nudged the client — you can also message them politely in the quest thread.'),
                        ],
                        route('quests.show', [$quest->getRouteKey()], absolute: true),
                        __('Open quest'),
                    )) {
                        $sent['awarded_no_escrow']++;
                    }
                }
            });
    }

    /**
     * @param  array<string, int>  $sent
     */
    private function processDeliveryPendingClientAction(array &$sent): void
    {
        Quest::query()
            ->where('status', QuestStatus::InProgress)
            ->where('escrow_status', 'funded')
            ->whereNotNull('delivered_at')
            ->whereNull('delivery_acknowledged_at')
            ->with('client')
            ->chunkById(50, function ($quests) use (&$sent): void {
                foreach ($quests as $quest) {
                    $due = $this->lifecycle->expectedCompletionAt($quest);
                    if (! $due) {
                        continue;
                    }

                    $autoReleaseAt = $due->copy()->addHours(72);
                    $warnFrom = $autoReleaseAt->copy()->subHours(24);

                    if (now()->lt($warnFrom)) {
                        continue;
                    }

                    $client = $quest->client;
                    if (! $client) {
                        continue;
                    }

                    if ($this->sendNudge(
                        $quest,
                        $client,
                        'delivery_pending_client_action',
                        __('24-hour notice before auto-release — “:title”', ['title' => $quest->title]),
                        [
                            __('Delivery was submitted and we have not recorded your approval or a dispute.'),
                            __('If the work meets the brief, mark the quest complete. If something is wrong, open a dispute within the next 24 hours.'),
                            __('After that window, escrow may release automatically under our Terms.'),
                        ],
                        route('quests.show', [$quest->getRouteKey()], absolute: true),
                        __('Review delivery'),
                    )) {
                        $sent['delivery_pending_client_action']++;
                    }
                }
            });
    }

    /**
     * @param  list<string>  $bodyLines
     */
    private function sendNudge(
        Quest $quest,
        User $recipient,
        string $nudgeType,
        string $subject,
        array $bodyLines,
        string $primaryUrl,
        string $primaryLabel,
    ): bool {
        if (QuestNudgeLog::query()
            ->where('quest_id', $quest->id)
            ->where('nudge_type', $nudgeType)
            ->where('recipient_user_id', $recipient->id)
            ->exists()) {
            return false;
        }

        DB::transaction(function () use ($quest, $recipient, $nudgeType, $subject, $bodyLines, $primaryUrl, $primaryLabel): void {
            $recipient->notify(new QuestEngagementNudgeNotification(
                quest: $quest,
                nudgeType: $nudgeType,
                subjectLine: $subject,
                bodyLines: $bodyLines,
                primaryUrl: $primaryUrl,
                primaryLabel: $primaryLabel,
            ));

            QuestNudgeLog::query()->create([
                'quest_id' => $quest->id,
                'nudge_type' => $nudgeType,
                'recipient_user_id' => $recipient->id,
                'channel' => 'mail',
                'subject' => $subject,
                'body' => implode("\n", $bodyLines),
                'meta' => ['primary_url' => $primaryUrl],
                'sent_at' => now(),
            ]);
        });

        return true;
    }
}
