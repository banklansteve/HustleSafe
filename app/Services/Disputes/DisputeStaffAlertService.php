<?php

namespace App\Services\Disputes;

use App\Enums\DisputeResolutionOption;
use App\Enums\QuestDisputeReason;
use App\Models\AdminNotification;
use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class DisputeStaffAlertService
{
    public function notifyAssigned(QuestDispute $dispute, User $staff): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $dispute->loadMissing(['quest']);
        $reference = $dispute->displayReference();
        $url = route('operations.disputes.index', ['q' => $dispute->uuid], false);

        $dedupe = "dispute_assigned:{$dispute->id}:{$staff->id}";
        if (AdminNotification::query()->where('admin_user_id', $staff->id)->where('data->dedupe_key', $dedupe)->exists()) {
            return;
        }

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'high',
            'title' => __('New dispute assigned'),
            'body' => __(':ref — :category, :amount. Open your dispute queue to investigate.', [
                'ref' => $reference,
                'category' => QuestDisputeReason::tryFrom((string) $dispute->reason)?->label() ?? __('Dispute'),
                'amount' => '₦'.number_format(((int) $dispute->disputed_amount_minor) / 100),
            ]),
            'action_label' => __('Open dispute'),
            'action_url' => $url,
            'data' => [
                'dedupe_key' => $dedupe,
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
            ],
        ]);
    }

    public function notifyClarificationRequested(QuestDispute $dispute, User $staff, User $superAdmin, ?string $note): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $reference = $dispute->displayReference();
        $url = route('operations.disputes.index', ['q' => $dispute->uuid], false);

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'high',
            'title' => __('Clarification requested'),
            'body' => __(':admin needs clarification on :ref before deciding.', [
                'admin' => $superAdmin->name,
                'ref' => $reference,
            ]).($note ? ' '.$note : ''),
            'action_label' => __('Open dispute workspace'),
            'action_url' => $url,
            'data' => [
                'dedupe_key' => 'dispute_clarification:'.$dispute->id.':'.now()->timestamp,
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
                'kind' => 'clarification',
            ],
        ]);
    }

    public function notifyReturnedForReview(QuestDispute $dispute, User $staff, User $superAdmin, ?string $note): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $reference = $dispute->displayReference();
        $url = route('operations.disputes.index', ['q' => $dispute->uuid], false);

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'high',
            'title' => __('More review requested'),
            'body' => __(':admin sent :ref back for further investigation.', [
                'admin' => $superAdmin->name,
                'ref' => $reference,
            ]).($note ? ' '.$note : ''),
            'action_label' => __('Continue investigation'),
            'action_url' => $url,
            'data' => [
                'dedupe_key' => 'dispute_returned:'.$dispute->id.':'.now()->timestamp,
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
                'kind' => 'more_review',
            ],
        ]);
    }

    public function notifyPartySelfResolved(QuestDispute $dispute, User $staff, string $outcome): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $reference = $dispute->displayReference();
        $url = route('operations.disputes.index', ['q' => $dispute->uuid], false);
        $label = app(DisputeResolutionOutcomeLabelService::class)->label($outcome) ?? $outcome;

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'normal',
            'title' => __('Parties resolved the dispute'),
            'body' => __(':ref was closed without a formal ruling — :outcome.', [
                'ref' => $reference,
                'outcome' => $label,
            ]),
            'action_label' => __('View dispute file'),
            'action_url' => $url,
            'data' => [
                'dedupe_key' => "dispute_party_resolved:{$dispute->id}:{$staff->id}",
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
                'kind' => 'party_self_resolved',
            ],
        ]);
    }

    public function notifyResolutionProposed(QuestDispute $dispute, User $staff, User $party, DisputeResolutionOption $option): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $reference = $dispute->displayReference();
        $url = route('operations.disputes.index', ['q' => $dispute->uuid], false);

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'normal',
            'title' => __('Party asked Customer Support to decide'),
            'body' => __(':party requested “:option” on :ref. Review the dispute file.', [
                'party' => $party->name,
                'option' => $option->label(),
                'ref' => $reference,
            ]),
            'action_label' => __('View dispute file'),
            'action_url' => $url,
            'data' => [
                'dedupe_key' => "dispute_resolution_proposed:{$dispute->id}:{$staff->id}:{$option->value}",
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
                'kind' => 'resolution_proposed',
            ],
        ]);
    }

    public function notifyPartiesAgreedOnProposal(QuestDispute $dispute, User $staff, DisputeResolutionOption $option): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $reference = $dispute->displayReference();
        $url = route('operations.disputes.index', ['q' => $dispute->uuid], false);

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'high',
            'title' => __('Both parties agreed on an outcome'),
            'body' => __(':ref — both parties proposed “:option”. Super Admin review is required to close the case.', [
                'ref' => $reference,
                'option' => $option->label(),
            ]),
            'action_label' => __('View dispute file'),
            'action_url' => $url,
            'data' => [
                'dedupe_key' => "dispute_parties_agreed:{$dispute->id}:{$staff->id}:{$option->value}",
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
                'kind' => 'parties_agreed',
            ],
        ]);
    }

    public function notifyPartyResolutionAcknowledged(QuestDispute $dispute, User $staff, User $superAdmin): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $reference = $dispute->displayReference();
        $url = route('operations.disputes.index', ['q' => $dispute->uuid], false);

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'normal',
            'title' => __('Party resolution acknowledged'),
            'body' => __(':admin acknowledged the party resolution on :ref.', [
                'admin' => $superAdmin->name,
                'ref' => $reference,
            ]),
            'action_label' => __('View dispute file'),
            'action_url' => $url,
            'data' => [
                'dedupe_key' => "dispute_party_ack:{$dispute->id}:{$staff->id}",
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
                'kind' => 'party_resolution_acknowledged',
            ],
        ]);
    }

    public function notifyNegotiationActivity(QuestDispute $dispute, User $staff, string $message): void
    {
        $this->staffDisputeAlert($dispute, $staff, 'negotiation_activity', __('Negotiation update'), $message);
    }

    public function notifyMutualAgreementPendingApproval(QuestDispute $dispute, User $staff, \App\Models\DisputeNegotiationOffer $offer): void
    {
        $this->staffDisputeAlert(
            $dispute,
            $staff,
            "mutual_pending:{$dispute->id}:{$staff->id}",
            __('Mutual agreement awaiting your approval'),
            __(':ref — parties agreed on :summary. Review escrow and approve.', [
                'ref' => $dispute->displayReference(),
                'summary' => $offer->summaryLabel(),
            ]),
        );
    }

    public function notifyEscalatedToMediation(QuestDispute $dispute, User $staff): void
    {
        $this->staffDisputeAlert(
            $dispute,
            $staff,
            "mediation:{$dispute->id}:{$staff->id}",
            __('Negotiation failed — mediation required'),
            __(':ref needs your mediation assessment using the full proposal history.', ['ref' => $dispute->displayReference()]),
        );
    }

    private function staffDisputeAlert(QuestDispute $dispute, User $staff, string $dedupeKey, string $title, string $body): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            'category' => 'disputes',
            'priority' => 'high',
            'title' => $title,
            'body' => $body,
            'action_label' => __('View dispute file'),
            'action_url' => route('operations.disputes.index', ['q' => $dispute->uuid], false),
            'data' => [
                'dedupe_key' => $dedupeKey,
                'dispute_id' => $dispute->id,
                'dispute_uuid' => $dispute->uuid,
            ],
        ]);
    }
}
