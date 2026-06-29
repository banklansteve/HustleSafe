<?php

namespace App\Services\Disputes;

use App\Models\AdminNotification;
use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class DisputeSuperAdminAlertService
{
    public function notifyDisputeOpened(QuestDispute $dispute, User $opener): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $dispute->loadMissing(['quest']);
        $quest = $dispute->quest;
        if ($quest === null) {
            return;
        }

        $reference = $dispute->displayReference();
        $url = route('admin.disputes.index', ['q' => $dispute->uuid], false);

        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->get(['id'])
            ->each(function (User $admin) use ($dispute, $opener, $quest, $reference, $url): void {
                $dedupe = "dispute_opened:{$dispute->id}:{$admin->id}";
                if (AdminNotification::query()->where('admin_user_id', $admin->id)->where('data->dedupe_key', $dedupe)->exists()) {
                    return;
                }

                AdminNotification::query()->create([
                    'admin_user_id' => $admin->id,
                    'category' => 'disputes',
                    'priority' => 'high',
                    'title' => __('New dispute filed'),
                    'body' => __(':name opened dispute :ref on “:quest”.', [
                        'name' => $opener->name,
                        'ref' => $reference,
                        'quest' => $quest->title,
                    ]),
                    'action_label' => __('Review dispute'),
                    'action_url' => $url,
                    'data' => [
                        'dedupe_key' => $dedupe,
                        'dispute_id' => $dispute->id,
                        'dispute_uuid' => $dispute->uuid,
                        'quest_id' => $quest->id,
                    ],
                ]);
            });
    }

    public function notifyAssessmentSubmitted(QuestDispute $dispute, User $staff): void
    {
        $this->notifySuperAdmins(
            $dispute,
            'dispute_assessment_submitted',
            __('Assessment submitted'),
            function () use ($dispute, $staff): string {
                $assessment = $dispute->latestSubmittedAssessment();
                $rec = $assessment?->recommendation?->label() ?? __('Pending');
                $share = $assessment?->recommended_client_share_percent;
                $shareText = $share !== null ? " ({$share}/".(100 - $share).')' : '';

                return __(':staff submitted an assessment on :ref. Recommendation: :rec:share', [
                    'staff' => $staff->name,
                    'ref' => $dispute->displayReference(),
                    'rec' => $rec,
                    'share' => $shareText,
                ]);
            },
            __('Review now'),
        );
    }

    public function notifyReadyForDecision(QuestDispute $dispute, User $staff): void
    {
        $this->notifySuperAdmins(
            $dispute,
            'dispute_ready_for_decision',
            __('Ready for Super Admin review'),
            function () use ($dispute, $staff): string {
                $assessment = $dispute->latestSubmittedAssessment();
                $rec = $assessment?->recommendation?->label() ?? __('See assessment');

                return __(':ref is ready for your decision. Staff: :staff. Recommendation: :rec.', [
                    'ref' => $dispute->displayReference(),
                    'staff' => $staff->name,
                    'rec' => $rec,
                ]);
            },
            __('Review now'),
        );
    }

    public function notifyReassignmentRequested(QuestDispute $dispute, User $staff, string $reason): void
    {
        $this->notifySuperAdmins(
            $dispute,
            'dispute_reassign_requested',
            __('Staff requested reassignment'),
            fn (): string => __(
                ':staff requested reassignment on :ref. Reason: :reason',
                ['staff' => $staff->name, 'ref' => $dispute->displayReference(), 'reason' => $reason],
            ),
            __('Review request'),
        );
    }

    public function notifyStaffGuidanceResponse(QuestDispute $dispute, User $staff, string $guidanceType, string $response): void
    {
        $title = $guidanceType === 'clarification'
            ? __('Staff clarification received')
            : __('Staff review update received');

        $this->notifySuperAdmins(
            $dispute,
            'dispute_staff_guidance_response',
            $title,
            fn (): string => __(
                ':staff responded on :ref: :response',
                ['staff' => $staff->name, 'ref' => $dispute->displayReference(), 'response' => $response],
            ),
            __('Open dispute'),
        );
    }

    public function notifyPartySelfResolved(QuestDispute $dispute, string $outcome): void
    {
        $label = app(DisputeResolutionOutcomeLabelService::class)->label($outcome) ?? $outcome;

        $this->notifySuperAdmins(
            $dispute,
            'dispute_party_self_resolved',
            __('Parties resolved without a ruling'),
            fn (): string => __(
                ':ref was closed by the parties — :outcome. Review and acknowledge when ready.',
                ['ref' => $dispute->displayReference(), 'outcome' => $label],
            ),
            __('Review dispute'),
        );
    }

    public function notifyMutualAgreementPendingApproval(QuestDispute $dispute, \App\Models\DisputeNegotiationOffer $offer): void
    {
        $this->notifySuperAdmins(
            $dispute,
            'dispute_mutual_pending',
            __('Mutual agreement submitted'),
            fn (): string => __(':ref — parties agreed: :summary. Staff will verify first.', [
                'ref' => $dispute->displayReference(),
                'summary' => $offer->summaryLabel(),
            ]),
            __('Review dispute'),
        );
    }

    public function notifyAppealUnderReview(QuestDispute $dispute, \App\Models\DisputeAppeal $appeal): void
    {
        $this->notifySuperAdmins(
            $dispute,
            'dispute_appeal_filed',
            __('Appeal requires review'),
            fn (): string => __(':ref — :party filed an appeal. Final review is binding.', [
                'ref' => $dispute->displayReference(),
                'party' => $appeal->filedBy?->name ?? __('A party'),
            ]),
            __('Review appeal'),
        );
    }

    /**
     * @param  callable(): string  $bodyBuilder
     */
    private function notifySuperAdmins(QuestDispute $dispute, string $eventKey, string $title, callable $bodyBuilder, string $actionLabel): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $dispute->loadMissing(['quest']);
        $url = route('admin.disputes.index', ['q' => $dispute->uuid], false);
        $body = $bodyBuilder();

        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->get(['id'])
            ->each(function (User $admin) use ($dispute, $eventKey, $title, $body, $actionLabel, $url): void {
                $dedupe = "{$eventKey}:{$dispute->id}:{$admin->id}:".now()->format('Y-m-d-H');
                if (AdminNotification::query()->where('admin_user_id', $admin->id)->where('data->dedupe_key', $dedupe)->exists()) {
                    return;
                }

                AdminNotification::query()->create([
                    'admin_user_id' => $admin->id,
                    'category' => 'disputes',
                    'priority' => 'high',
                    'title' => $title,
                    'body' => $body,
                    'action_label' => $actionLabel,
                    'action_url' => $url,
                    'data' => [
                        'dedupe_key' => $dedupe,
                        'dispute_id' => $dispute->id,
                        'dispute_uuid' => $dispute->uuid,
                    ],
                ]);
            });
    }
}
