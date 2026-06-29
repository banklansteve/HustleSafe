<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeManagementStatus;
use App\Enums\QuestDisputeStatus;
use App\Models\QuestDispute;
use App\Models\User;

class DisputePartyWorkflowService
{
    /**
     * @return array{
     *     current_key: string,
     *     headline: string,
     *     next_steps: string,
     *     stages: list<array{key: string, label: string, description: string, state: string}>
     * }
     */
    public function forDispute(QuestDispute $dispute, ?User $viewer): array
    {
        $dispute->loadMissing('quest');
        $selfHours = (int) config('disputes.self_resolution_response_hours', 48);
        $formalHours = (int) config('disputes.formal_no_response_ruling_hours', 72);

        $isClosed = in_array($dispute->status, [
            QuestDisputeStatus::Resolved,
            QuestDisputeStatus::ClosedWithdrawn,
        ], true) || $dispute->management_status === QuestDisputeManagementStatus::Finalized;

        $inSelfResolution = $dispute->status === QuestDisputeStatus::SelfResolving;
        $inEscalated = $dispute->status === QuestDisputeStatus::Escalated;
        $staffActive = in_array($dispute->management_status, [
            QuestDisputeManagementStatus::Open,
            QuestDisputeManagementStatus::PendingResponse,
            QuestDisputeManagementStatus::UnderReview,
        ], true);
        $awaitingDecision = $dispute->management_status === QuestDisputeManagementStatus::ReadyForDecision;
        $decided = in_array($dispute->management_status, [
            QuestDisputeManagementStatus::Resolved,
            QuestDisputeManagementStatus::Closed,
            QuestDisputeManagementStatus::Finalized,
        ], true);

        $awaitingViewer = $viewer !== null
            && (int) $dispute->awaiting_user_id === (int) $viewer->id
            && $inSelfResolution;

        $stages = [
            [
                'key' => 'self_resolution',
                'label' => __('Self-resolution'),
                'description' => __('Both parties share evidence, messages, and settlement offers. Each turn has :hours hours.', ['hours' => $selfHours]),
                'state' => $this->stageState($isClosed || $decided, $inSelfResolution, false),
            ],
            [
                'key' => 'staff_review',
                'label' => __('Customer Support review'),
                'description' => __('Our Customer Support team reviews evidence, may request more from either party, and prepares a recommendation.'),
                'state' => $this->stageState($isClosed || $decided, $staffActive || $inEscalated, $inSelfResolution && ! $isClosed),
            ],
            [
                'key' => 'super_admin_decision',
                'label' => __('Super Admin decision'),
                'description' => __('A Super Admin reviews the staff assessment and issues a binding decision. Escrow is moved accordingly.'),
                'state' => $this->stageState($isClosed || $decided, $awaitingDecision, ($staffActive || $inEscalated) && ! $awaitingDecision),
            ],
            [
                'key' => 'closed',
                'label' => __('Closed'),
                'description' => __('Outcome applied. An appeal window may apply before the file is finalized.'),
                'state' => $isClosed || $decided ? 'current' : 'upcoming',
            ],
        ];

        if ($isClosed || $decided) {
            $currentKey = 'closed';
            $headline = __('This dispute is closed');
            $nextSteps = __('The recorded outcome has been applied. If an appeal window is still open, follow the notice in your inbox.');
        } elseif ($awaitingDecision) {
            $currentKey = 'super_admin_decision';
            $headline = __('Awaiting Super Admin decision');
            $nextSteps = __('Staff investigation is complete. You will be notified when a final decision is issued and escrow is updated.');
        } elseif ($staffActive || $inEscalated) {
            $currentKey = 'staff_review';
            $headline = __('Under Customer Support review');
            $nextSteps = __('Respond promptly if Customer Support requests evidence. You will be notified before any final decision.');
        } else {
            $currentKey = 'self_resolution';
            $headline = __('Self-resolution in progress');
            $nextSteps = $awaitingViewer
                ? __('It is your turn to respond — post an update, share evidence, or propose a settlement split before the timer expires.')
                : __('Waiting for the other party to respond. You can still add evidence or propose a settlement at any time.');
        }

        if ($inEscalated && $dispute->ruling_required_by) {
            $nextSteps .= ' '.__('Formal evidence window closes :when.', [
                'when' => $dispute->ruling_required_by->timezone('Africa/Lagos')->format('j M Y, g:i A'),
            ]);
        } elseif ($inSelfResolution && $dispute->response_required_by) {
            $nextSteps .= ' '.__('Current response deadline: :when.', [
                'when' => $dispute->response_required_by->timezone('Africa/Lagos')->format('j M Y, g:i A'),
            ]);
        }

        return [
            'current_key' => $currentKey,
            'headline' => $headline,
            'next_steps' => trim($nextSteps),
            'self_resolution_hours' => $selfHours,
            'formal_ruling_hours' => $formalHours,
            'stages' => $stages,
        ];
    }

    protected function stageState(bool $closed, bool $active, bool $completed): string
    {
        if ($closed) {
            return 'completed';
        }
        if ($active) {
            return 'current';
        }
        if ($completed) {
            return 'completed';
        }

        return 'upcoming';
    }
}
