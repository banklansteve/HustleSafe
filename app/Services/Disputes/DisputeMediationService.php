<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Models\DisputeEvent;
use App\Models\DisputeMediationSession;
use App\Models\QuestDispute;
use App\Models\User;
use App\Services\Disputes\DisputePartyNotifier;

class DisputeMediationService
{
    public function __construct(
        private readonly DisputePartyNotifier $partyNotifier,
        private readonly DisputeSmsNotifier $smsNotifier,
    ) {}

    public function schedule(QuestDispute $dispute, User $actor, array $data): DisputeMediationSession
    {
        $session = DisputeMediationSession::query()->create([
            'quest_dispute_id' => $dispute->id,
            'opened_by_user_id' => $actor->id,
            'status' => 'scheduled',
            'scheduled_at' => $data['scheduled_at'] ?? now()->addDay(),
            'meeting_url' => $data['meeting_url'] ?? null,
            'instructions' => $data['instructions'] ?? __('Both parties should join the scheduled call to work toward a settlement with HustleSafe facilitation.'),
        ]);

        $dispute->forceFill([
            'outcome_action' => 'mediation',
            'management_status' => QuestDisputeManagementStatus::PendingResponse,
        ])->save();

        $this->recordEvent($dispute, $actor, 'mediation.scheduled', [
            'session_id' => $session->id,
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
        ]);

        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $when = $session->scheduled_at?->timezone('Africa/Lagos')->format('M j, Y g:i A') ?? __('soon');
        foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
            $this->partyNotifier->notify(
                $party,
                $dispute,
                __('Mediation session scheduled'),
                __('A facilitated mediation call was scheduled for your dispute.'),
                $session->instructions,
                __('View mediation details'),
                'both',
            );
            $this->smsNotifier->notifyMediation($party, $dispute, $when);
        }

        return $session;
    }

    public function complete(QuestDispute $dispute, User $actor, array $data): DisputeMediationSession
    {
        $session = DisputeMediationSession::query()
            ->where('quest_dispute_id', $dispute->id)
            ->where('status', 'scheduled')
            ->latest('id')
            ->firstOrFail();

        $session->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        $this->recordEvent($dispute, $actor, 'mediation.completed', ['session_id' => $session->id, 'notes' => $data['notes'] ?? null]);

        return $session->fresh();
    }

    private function recordEvent(QuestDispute $dispute, User $actor, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
