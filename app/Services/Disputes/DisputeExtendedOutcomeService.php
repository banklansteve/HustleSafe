<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputeStatus;
use App\Enums\QuestStatus;
use App\Models\DisputeEvent;
use App\Models\QuestDispute;
use App\Models\User;

class DisputeExtendedOutcomeService
{
    public function __construct(private readonly DisputePartyNotifier $partyNotifier) {}

    public function forceRevision(QuestDispute $dispute, User $actor, array $data): QuestDispute
    {
        $dispute->loadMissing('quest');
        $quest = $dispute->quest;
        if ($quest) {
            $quest->forceFill(['status' => QuestStatus::InProgress])->save();
        }

        $dispute->forceFill([
            'outcome_action' => 'force_revision',
            'management_status' => QuestDisputeManagementStatus::PendingResponse,
            'status' => QuestDisputeStatus::Open,
            'resolved_at' => null,
            'management_resolved_at' => null,
            'super_admin_decision_notes' => $data['decision_notes'] ?? $data['instructions'] ?? null,
        ])->save();

        $this->notifyParties($dispute, __('Revision required'), $data['instructions'] ?? $data['decision_notes'] ?? __('The dispute was resolved by requiring additional revision work.'));
        $this->recordEvent($dispute, $actor, 'outcome.force_revision', ['instructions' => $data['instructions'] ?? $data['decision_notes'] ?? null]);

        return $dispute->fresh();
    }

    public function extendDeadline(QuestDispute $dispute, User $actor, array $data): QuestDispute
    {
        $until = $data['extended_deadline_at'] ?? now()->addDays((int) ($data['days'] ?? 7));
        $dispute->forceFill([
            'outcome_action' => 'extend_deadline',
            'extended_deadline_at' => $until,
            'response_required_by' => $until,
            'management_status' => QuestDisputeManagementStatus::PendingResponse,
        ])->save();

        $this->notifyParties($dispute, __('Deadline extended'), __('Your dispute timeline was extended to :date.', [
            'date' => $until->timezone('Africa/Lagos')->format('M j, Y'),
        ]));
        $this->recordEvent($dispute, $actor, 'outcome.deadline_extended', ['until' => $until->toIso8601String()]);

        return $dispute->fresh();
    }

    public function terminateContract(QuestDispute $dispute, User $actor, array $data): QuestDispute
    {
        $dispute->loadMissing('quest');
        $quest = $dispute->quest;
        if ($quest) {
            $quest->forceFill([
                'status' => QuestStatus::CancelledByAdmin,
                'closure_type' => 'dispute_termination',
            ])->save();
        }

        $dispute->forceFill([
            'outcome_action' => 'terminate_contract',
            'management_status' => QuestDisputeManagementStatus::Closed,
            'status' => QuestDisputeStatus::Resolved,
            'resolved_at' => now(),
            'management_resolved_at' => now(),
            'super_admin_decision_notes' => $data['decision_notes'] ?? $data['notes'] ?? $dispute->super_admin_decision_notes,
        ])->save();

        $this->notifyParties($dispute, __('Contract terminated'), $data['decision_notes'] ?? $data['notes'] ?? __('The contract was terminated following dispute resolution.'));
        $this->recordEvent($dispute, $actor, 'outcome.contract_terminated', ['notes' => $data['decision_notes'] ?? $data['notes'] ?? null]);

        return $dispute->fresh();
    }

    private function notifyParties(QuestDispute $dispute, string $headline, string $body): void
    {
        $dispute->loadMissing('quest.client', 'quest.freelancer');
        foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
            $this->partyNotifier->notify($party, $dispute, $headline, $body, null, __('View dispute'), 'both');
        }
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
