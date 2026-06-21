<?php

namespace App\Services\Quest;

use App\Enums\EscrowDeliveryStage;
use App\Enums\QuestInstallmentStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestDeliverySubmission;
use App\Models\User;
use App\Support\EscrowAutoReleasePolicy;
use App\Support\EscrowReleasePolicy;
use App\Support\NgnMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestDeliveryLifecycleService
{
    public function __construct(
        protected QuestCompletionScheduleService $schedule,
        protected QuestRecurringEngagementService $recurring,
    ) {}

    public function reviewHours(): int
    {
        return EscrowAutoReleasePolicy::releaseHours();
    }

    public function stage(Quest $quest): EscrowDeliveryStage
    {
        $recurring = $this->recurring->isRecurring($quest);
        $questCompleted = ($quest->status?->value ?? (string) $quest->status) === QuestStatus::Completed->value;

        if ($questCompleted || ($quest->escrow_status === 'released' && ! $recurring)) {
            return EscrowDeliveryStage::CompletedPaid;
        }

        if ($recurring && $this->recurring->allInstallmentsReleased($quest)) {
            return EscrowDeliveryStage::CompletedPaid;
        }

        if ($quest->delivery_acknowledged_at !== null) {
            return EscrowDeliveryStage::ApprovedReleasing;
        }

        if ($quest->delivery_revision_requested_at !== null && $quest->delivered_at === null) {
            return EscrowDeliveryStage::RevisionRequested;
        }

        if ($quest->delivered_at !== null && $quest->delivery_revision_requested_at === null) {
            return EscrowDeliveryStage::AwaitingReview;
        }

        if ($quest->delivery_revision_requested_at !== null) {
            return EscrowDeliveryStage::RevisionRequested;
        }

        if (in_array($quest->escrow_status, ['funded', 'partially_released'], true)
            && ($quest->status?->value ?? (string) $quest->status) === QuestStatus::InProgress->value) {
            return EscrowDeliveryStage::WorkInProgress;
        }

        return EscrowDeliveryStage::WorkInProgress;
    }

    public function reviewDeadlineAt(Quest $quest): ?Carbon
    {
        if ($quest->delivery_review_deadline_at !== null) {
            return $quest->delivery_review_deadline_at->copy();
        }

        if ($quest->delivered_at === null) {
            return null;
        }

        return EscrowAutoReleasePolicy::releaseAt($quest->delivered_at);
    }

    public function secondsUntilReviewDeadline(Quest $quest): int
    {
        $deadline = $this->reviewDeadlineAt($quest);
        if ($deadline === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($deadline, false));
    }

    public function secondsUntilWorkDeadline(Quest $quest): int
    {
        $anchor = $this->schedule->engagementAnchorAt($quest);
        if ($anchor === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($anchor, false));
    }

    public function canFreelancerSubmit(Quest $quest, ?User $freelancer): bool
    {
        if ($freelancer === null || (int) $quest->freelancer_id !== (int) $freelancer->id) {
            return false;
        }

        if (($quest->status?->value ?? (string) $quest->status) !== QuestStatus::InProgress->value) {
            return false;
        }

        if (! in_array($quest->escrow_status, ['funded', 'partially_released'], true)) {
            return false;
        }

        if ($quest->delivery_acknowledged_at !== null) {
            return false;
        }

        if ($this->recurring->isRecurring($quest)) {
            $installment = $this->recurring->currentInstallment($quest);
            if ($installment === null) {
                return false;
            }

            $stage = $this->stage($quest);

            return in_array($installment->status, [
                QuestInstallmentStatus::Active,
                QuestInstallmentStatus::RevisionRequested,
            ], true) && in_array($stage, [EscrowDeliveryStage::WorkInProgress, EscrowDeliveryStage::RevisionRequested], true);
        }

        $stage = $this->stage($quest);

        return in_array($stage, [EscrowDeliveryStage::WorkInProgress, EscrowDeliveryStage::RevisionRequested], true);
    }

    public function canClientApprove(Quest $quest, ?User $client): bool
    {
        if ($client === null || (int) $quest->client_id !== (int) $client->id) {
            return false;
        }

        if ($this->stage($quest) !== EscrowDeliveryStage::AwaitingReview) {
            return false;
        }

        return $quest->delivery_acknowledged_at === null;
    }

    public function canClientRequestRevision(Quest $quest, ?User $client): bool
    {
        return $this->canClientApprove($quest, $client);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitDeliverable(Quest $quest, User $freelancer, array $data): QuestDeliverySubmission
    {
        if (! $this->canFreelancerSubmit($quest, $freelancer)) {
            throw ValidationException::withMessages(['quest' => [__('You cannot submit a deliverable for this quest right now.')]]);
        }

        return DB::transaction(function () use ($quest, $freelancer, $data): QuestDeliverySubmission {
            $quest = Quest::query()->whereKey($quest->id)->lockForUpdate()->firstOrFail();

            $revisionNumber = max(1, (int) $quest->delivery_submission_count + 1);
            $submittedAt = now();

            $submission = QuestDeliverySubmission::query()->create([
                'quest_id' => $quest->id,
                'freelancer_id' => $freelancer->id,
                'revision_number' => $revisionNumber,
                'summary' => trim((string) ($data['summary'] ?? '')),
                'delivery_url' => trim((string) ($data['delivery_url'] ?? '')) ?: null,
                'attachments' => $data['attachments'] ?? null,
                'submitted_at' => $submittedAt,
            ]);

            $reviewDeadline = EscrowAutoReleasePolicy::releaseAt($submittedAt);

            $quest->update([
                'delivered_at' => $submittedAt,
                'delivery_review_deadline_at' => $reviewDeadline,
                'delivery_revision_requested_at' => null,
                'delivery_revision_requested_by' => null,
                'delivery_revision_note' => null,
                'delivery_submission_count' => $revisionNumber,
                'latest_delivery_submission_id' => $submission->id,
            ]);

            if ($this->recurring->isRecurring($quest)) {
                $installment = $this->recurring->currentInstallment($quest);
                if ($installment !== null) {
                    $this->recurring->markInstallmentDelivered($quest, $installment, $submission, $submittedAt, $reviewDeadline);
                }
            }

            return $submission;
        });
    }

    public function requestRevision(Quest $quest, User $client, string $note): void
    {
        if (! $this->canClientRequestRevision($quest, $client)) {
            throw ValidationException::withMessages(['quest' => [__('You cannot request revisions right now.')]]);
        }

        if ($this->recurring->isRecurring($quest)) {
            $installment = $this->recurring->currentInstallment($quest);
            if ($installment !== null) {
                $this->recurring->markInstallmentRevisionRequested($quest, $installment, $client, $note);

                return;
            }
        }

        $quest->update([
            'delivery_revision_requested_at' => now(),
            'delivery_revision_requested_by' => $client->id,
            'delivery_revision_note' => trim($note),
            'delivered_at' => null,
            'delivery_review_deadline_at' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function latestSubmissionPayload(Quest $quest): ?array
    {
        $quest->loadMissing('latestDeliverySubmission');

        $submission = $quest->latestDeliverySubmission;
        if ($submission === null) {
            return null;
        }

        return [
            'id' => $submission->id,
            'revision_number' => $submission->revision_number,
            'summary' => $submission->summary,
            'delivery_url' => $submission->delivery_url,
            'attachments' => $submission->attachments ?? [],
            'submitted_at' => $submission->submitted_at?->timezone(config('app.timezone'))->toIso8601String(),
            'submitted_label' => $submission->submitted_at?->timezone(config('app.timezone'))->format('j M Y, g:i A'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uiPayload(Quest $quest, ?User $viewer): array
    {
        $isClient = $viewer !== null && (int) $viewer->id === (int) $quest->client_id;
        $isFreelancer = $viewer !== null && (int) $viewer->id === (int) $quest->freelancer_id;
        $funded = in_array($quest->escrow_status, ['funded', 'partially_released', 'released'], true);
        $inProgress = ($quest->status?->value ?? (string) $quest->status) === QuestStatus::InProgress->value;
        $stage = $this->stage($quest);
        $schedule = $this->schedule->toPayload($quest);
        $reviewDeadline = $this->reviewDeadlineAt($quest);
        $releasePolicy = EscrowReleasePolicy::uiPayload($quest, $viewer);
        $recurringPayload = $this->recurring->uiPayload($quest, $viewer);

        $escrowMinor = EscrowReleasePolicy::escrowAmountMinor($quest);
        $releaseAmountMinor = $this->recurring->isRecurring($quest)
            ? ($recurringPayload['current_installment']['amount_minor'] ?? (int) ($quest->installment_amount_minor ?? 0))
            : $escrowMinor;

        return [
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'show_panel' => $funded && ($inProgress || $stage === EscrowDeliveryStage::CompletedPaid || $stage === EscrowDeliveryStage::ApprovedReleasing),
            'is_client' => $isClient,
            'is_freelancer' => $isFreelancer,
            'review_hours' => $this->reviewHours(),
            'work_schedule' => $schedule,
            'expected_delivery_at' => $schedule['engagement_anchor_at'] ?? null,
            'expected_delivery_label' => $schedule['engagement_anchor_label'] ?? null,
            'seconds_until_work_deadline' => $this->secondsUntilWorkDeadline($quest),
            'review_deadline_at' => $reviewDeadline?->toIso8601String(),
            'review_deadline_label' => $reviewDeadline?->timezone(config('app.timezone'))->format('j M Y, g:i A'),
            'seconds_until_review_deadline' => $this->secondsUntilReviewDeadline($quest),
            'auto_release_plain_english' => __('If you do nothing for :hours hours after the worker submits, your money may be released automatically — unless you raise a complaint.', [
                'hours' => $this->reviewHours(),
            ]),
            'timeline_notice' => ($recurringPayload['is_recurring'] ?? false)
                ? (string) ($recurringPayload['plain_english'] ?? '')
                : __('Your money stays safe until the worker submits the finished job and you approve it (or the review period ends with no complaint).'),
            'delivery_adjustment_rules' => [
                'freelancer_adjustments' => [
                    'max' => \App\Services\Contracts\ContractDeliveryExtensionService::MAX_DATE_ADJUSTMENTS,
                    'max_extension_days' => \App\Services\Contracts\ContractDeliveryExtensionService::MAX_EXTENSION_DAYS,
                    'max_reduction_days' => \App\Services\Contracts\ContractDeliveryExtensionService::MAX_REDUCTION_DAYS,
                    'label' => __('Workers can ask to change the finish date up to :max times (more time or finish sooner). Each change needs your approval. Max :ext days extra or :red days earlier per request.', [
                        'max' => \App\Services\Contracts\ContractDeliveryExtensionService::MAX_DATE_ADJUSTMENTS,
                        'ext' => \App\Services\Contracts\ContractDeliveryExtensionService::MAX_EXTENSION_DAYS,
                        'red' => \App\Services\Contracts\ContractDeliveryExtensionService::MAX_REDUCTION_DAYS,
                    ]),
                ],
                'client_amendments' => [
                    'label' => __('You can also suggest a new finish date through a contract change — both of you must agree.'),
                ],
            ],
            'latest_submission' => $this->latestSubmissionPayload($quest),
            'revision_requested' => $quest->delivery_revision_requested_at !== null,
            'revision_note' => $quest->delivery_revision_note,
            'revision_requested_at' => $quest->delivery_revision_requested_at?->toIso8601String(),
            'can_submit_deliverable' => $this->canFreelancerSubmit($quest, $viewer),
            'can_approve' => $this->canClientApprove($quest, $viewer),
            'can_request_revision' => $this->canClientRequestRevision($quest, $viewer),
            'can_open_dispute_from_review' => $isClient && $stage === EscrowDeliveryStage::AwaitingReview,
            'approved_at' => $quest->delivery_acknowledged_at?->toIso8601String(),
            'funds_released_at' => $quest->funds_released_at?->toIso8601String(),
            'escrow_amount_formatted' => NgnMoney::format($escrowMinor),
            'escrow_amount_minor' => $escrowMinor,
            'release_amount_formatted' => NgnMoney::format((int) $releaseAmountMinor),
            'recurring_engagement' => $recurringPayload,
            'release' => $releasePolicy,
            'show_client_actions' => $isClient && $stage === EscrowDeliveryStage::AwaitingReview,
            'show_freelancer_submit' => $isFreelancer && $this->canFreelancerSubmit($quest, $viewer),
        ];
    }
}
