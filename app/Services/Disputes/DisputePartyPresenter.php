<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputeStatus;
use App\Models\DisputeEvent;
use App\Models\DisputeMessage;
use App\Models\QuestDispute;
use Illuminate\Support\Collection;

class DisputePartyPresenter
{
    /** @var list<string> */
    private const VISIBLE_ACTIONS = [
        'dispute.opened',
        'dispute.escalated_silence',
        'dispute.auto_timed_split',
        'dispute.settlement_offered',
        'dispute.settlement_accepted',
        'dispute.settlement_declined',
        'dispute.mutual_resolve_ack',
        'staff_acknowledged',
        'management.auto_assigned',
        'staff_claimed',
        'staff_notice',
        'staff_contact',
        'staff_evidence_request',
        'staff_awaiting_info',
        'super_admin.evidence_request',
        'super_admin.direct_message',
        'super_admin.decision_executed',
        'dispute_ruling_executed',
        'management.finalized',
        'outcome.force_revision',
        'outcome.deadline_extended',
        'outcome.contract_terminated',
        'mediation.scheduled',
        'mediation.completed',
        'super_admin.hold',
        'super_admin.hold_released',
    ];

    public function statusLabel(QuestDispute $dispute): string
    {
        if ($dispute->status === QuestDisputeStatus::Resolved) {
            return (string) __('Resolved');
        }

        if ($dispute->status === QuestDisputeStatus::ClosedWithdrawn) {
            return (string) __('Withdrawn');
        }

        if ($dispute->management_status === QuestDisputeManagementStatus::Finalized) {
            return (string) __('Closed');
        }

        if ($dispute->management_status === QuestDisputeManagementStatus::ReadyForDecision
            || $dispute->status === QuestDisputeStatus::AwaitingRuling) {
            return (string) __('Final decision pending');
        }

        if ($dispute->status === QuestDisputeStatus::Escalated
            || $dispute->management_status?->isStaffActive()) {
            return (string) __('Customer Support review');
        }

        if ($dispute->status === QuestDisputeStatus::SelfResolving) {
            return (string) __('Self-resolution');
        }

        return (string) __('Open');
    }

    /**
     * @param  Collection<int, DisputeEvent>  $events
     * @return list<array{action: string, action_label: string, created_at: ?string}>
     */
    public function visibleEvents(Collection $events): array
    {
        return $events
            ->filter(fn (DisputeEvent $event) => in_array($event->action, self::VISIBLE_ACTIONS, true))
            ->map(fn (DisputeEvent $event) => [
                'action' => $event->action,
                'action_label' => $this->partyEventLabel($event->action),
                'created_at' => $event->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, DisputeMessage>  $messages
     * @return list<array<string, mixed>>
     */
    public function visibleMessages(Collection $messages, QuestDispute $dispute): array
    {
        $openingSummary = trim((string) $dispute->opening_summary);
        $openerId = (int) $dispute->opened_by_user_id;
        $skippedOpeningNarrative = false;

        return $messages
            ->filter(function (DisputeMessage $message) use ($openingSummary, $openerId, &$skippedOpeningNarrative): bool {
                if ($skippedOpeningNarrative) {
                    return true;
                }

                $isOpeningNarrative = (int) $message->user_id === $openerId
                    && $message->kind->value === 'narrative'
                    && $openingSummary !== ''
                    && trim((string) $message->body) === $openingSummary;

                if ($isOpeningNarrative) {
                    $skippedOpeningNarrative = true;

                    return false;
                }

                return true;
            })
            ->map(fn (DisputeMessage $message) => [
                'id' => $message->id,
                'kind' => $message->kind->value,
                'kind_label' => $this->messageKindLabel($message->kind->value),
                'body' => $message->body,
                'structured_key' => $message->structured_key,
                'structured_payload' => $message->structured_payload,
                'created_at' => $message->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'user' => $message->user ? [
                    'id' => $message->user->id,
                    'name' => $message->user->name,
                    'first_name' => $message->user->first_name,
                ] : null,
                'is_system' => $message->user_id === null,
            ])
            ->values()
            ->all();
    }

    public function partyEventLabel(string $action): string
    {
        return match ($action) {
            'dispute.opened' => __('Dispute opened'),
            'management.auto_assigned', 'staff_claimed', 'staff_acknowledged' => __('Customer Support is reviewing your case'),
            'staff_notice', 'staff_contact', 'super_admin.direct_message' => __('Message from Customer Support'),
            'staff_evidence_request', 'super_admin.evidence_request', 'staff_awaiting_info' => __('Customer Support requested more information'),
            'dispute.escalated_silence' => __('Case escalated for formal review'),
            'dispute.auto_timed_split' => __('Case resolved with default split'),
            'dispute.settlement_offered' => __('Settlement offer submitted'),
            'dispute.settlement_accepted' => __('Settlement offer accepted'),
            'dispute.settlement_declined' => __('Settlement offer declined'),
            'dispute.mutual_resolve_ack' => __('Party agreed to mutual resolve'),
            'super_admin.decision_executed', 'dispute_ruling_executed' => __('Final decision issued'),
            'management.finalized' => __('Dispute closed'),
            'outcome.force_revision' => __('Revision ordered'),
            'outcome.deadline_extended' => __('Deadline extended'),
            'outcome.contract_terminated' => __('Contract ended'),
            'mediation.scheduled' => __('Mediation session scheduled'),
            'mediation.completed' => __('Mediation session completed'),
            'super_admin.hold' => __('Case temporarily on hold'),
            'super_admin.hold_released' => __('Case review resumed'),
            default => app(DisputeEventLabelService::class)->label($action),
        };
    }

    public function messageKindLabel(string $kind): string
    {
        return match ($kind) {
            'narrative' => __('Update'),
            'evidence' => __('Evidence'),
            'structured_response' => __('Structured response'),
            'settlement_note' => __('Settlement note'),
            'system' => __('System'),
            default => str_replace('_', ' ', $kind),
        };
    }
}
