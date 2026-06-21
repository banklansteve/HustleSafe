<?php

namespace App\Services\Contracts;

use App\Enums\ContractAmendmentType;
use App\Enums\ContractStatus;
use App\Enums\DeliveryDateAdjustmentType;
use App\Enums\DeliveryExtensionReasonCategory;
use App\Enums\DeliveryExtensionStatus;
use App\Jobs\ProcessDeliveryExtensionSubmissionJob;
use App\Models\FreelancerDeliveryExtensionLog;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestContractAmendment;
use App\Models\QuestContractDeliveryExtension;
use App\Models\User;
use App\Notifications\ContractDeliveryExtensionClientNotification;
use App\Notifications\ContractDeliveryExtensionFreelancerNotification;
use App\Services\Admin\AdminActivityFeedService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractDeliveryExtensionService
{
    /** Total date-change requests (extensions + earlier finishes) allowed per contract. */
    public const MAX_EXTENSIONS = 2;

    public const MAX_DATE_ADJUSTMENTS = 2;

    public const CLIENT_RESPONSE_HOURS = 48;

    public const COUNTER_RESPONSE_HOURS = 24;

    public const MAX_EXTENSION_DAYS = 14;

    public const MAX_REDUCTION_DAYS = 14;

    public const PROMPT_AMBER_HOURS = 48;

    public function __construct(
        private readonly ContractEventLogger $events,
        private readonly AdminActivityFeedService $activityFeed,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function queueRequest(QuestContract $contract, User $freelancer, array $data, ?Request $request = null): QuestContractDeliveryExtension
    {
        $this->assertFreelancerCanRequest($contract, $freelancer);

        $originalDate = $contract->agreed_delivery_date;
        if ($originalDate === null) {
            throw ValidationException::withMessages(['contract' => __('This contract has no agreed delivery date.')]);
        }

        if (now()->gte($originalDate->copy()->endOfDay())) {
            throw ValidationException::withMessages(['contract' => __('The agreed delivery date has passed. Request an amendment or open a dispute instead.')]);
        }

        $proposed = Carbon::parse($data['proposed_delivery_date'], config('app.timezone'))->startOfDay();
        $adjustmentType = DeliveryDateAdjustmentType::from($data['adjustment_type'] ?? DeliveryDateAdjustmentType::Extension->value);

        $this->assertProposedDate($originalDate, $proposed, $adjustmentType);

        $reasonCategory = DeliveryExtensionReasonCategory::from($data['reason_category']);
        $clientAttributed = $reasonCategory === DeliveryExtensionReasonCategory::ClientRequestedChanges;

        if ($clientAttributed && empty($data['scope_change_message_id'])) {
            throw ValidationException::withMessages([
                'scope_change_message_id' => __('Please tag the conversation message where the client requested the scope change.'),
            ]);
        }

        $attachments = $this->storeProgressAttachments($contract, $data['progress_attachments'] ?? []);

        $extension = DB::transaction(function () use ($contract, $freelancer, $data, $originalDate, $proposed, $adjustmentType, $reasonCategory, $clientAttributed, $attachments, $request): QuestContractDeliveryExtension {
            $extensionNumber = $contract->delivery_extension_count + 1;

            if ($extensionNumber > self::MAX_DATE_ADJUSTMENTS) {
                throw ValidationException::withMessages(['contract' => __('You have used all :max date-change requests on this job.', ['max' => self::MAX_DATE_ADJUSTMENTS])]);
            }

            $extension = QuestContractDeliveryExtension::query()->create([
                'quest_contract_id' => $contract->id,
                'extension_number' => $extensionNumber,
                'adjustment_type' => $adjustmentType,
                'requested_by_user_id' => $freelancer->id,
                'reason_category' => $reasonCategory,
                'explanation' => $data['explanation'],
                'original_delivery_date' => $originalDate,
                'proposed_delivery_date' => $proposed,
                'status' => DeliveryExtensionStatus::PendingClient,
                'progress_note' => $data['include_progress'] ? ($data['progress_note'] ?? null) : null,
                'progress_attachments' => $attachments ?: null,
                'scope_change_message_id' => $clientAttributed ? ($data['scope_change_message_id'] ?? null) : null,
                'client_response_deadline_at' => now()->addHours(self::CLIENT_RESPONSE_HOURS),
                'client_attributed_delay' => $clientAttributed,
                'admin_monitoring_flagged' => $clientAttributed,
                'submitted_at' => now(),
            ]);

            $contract->update([
                'pending_extension_id' => $extension->id,
                'deadline_clock_paused_at' => now(),
                'original_agreed_delivery_date' => $contract->original_agreed_delivery_date ?? $originalDate,
            ]);

            $this->events->log($contract, 'contract.extension_requested', $freelancer, [
                'extension_id' => $extension->id,
                'extension_number' => $extensionNumber,
                'adjustment_type' => $adjustmentType->value,
                'proposed_date' => $proposed->toDateString(),
                'reason_category' => $reasonCategory->value,
            ], $request);

            return $extension;
        });

        ProcessDeliveryExtensionSubmissionJob::dispatch($extension->id);

        return $extension;
    }

    public function finalizeSubmission(QuestContractDeliveryExtension $extension): void
    {
        $extension->loadMissing(['contract.client', 'contract.freelancer', 'contract.quest', 'scopeChangeMessage']);
        $contract = $extension->contract;
        if ($contract === null) {
            return;
        }

        $contract->client?->notify(new ContractDeliveryExtensionClientNotification($contract, $extension));

        if ($extension->client_attributed_delay) {
            $this->activityFeed->record(
                category: 'contracts',
                eventKey: 'contract.client_attributed_delay',
                title: __('Client-attributed delay — :ref', ['ref' => $contract->reference_code]),
                summary: __('Freelancer requested a delivery extension citing client-requested scope changes on contract :ref.', ['ref' => $contract->reference_code]),
                entities: [
                    ['type' => 'contract', 'id' => $contract->id, 'label' => $contract->reference_code],
                    ['type' => 'user', 'id' => $contract->freelancer_id, 'label' => $contract->freelancer?->name],
                ],
                metadata: [
                    'extension_id' => $extension->id,
                    'reason_category' => $extension->reason_category->value,
                    'scope_change_message_id' => $extension->scope_change_message_id,
                    'signal' => 'Client-attributed delay',
                ],
                subjectType: QuestContract::class,
                subjectId: $contract->id,
                severity: 'info',
            );
        }

        $this->logFreelancerOutcome($extension, 'submitted');
    }

    public function clientAccept(QuestContract $contract, QuestContractDeliveryExtension $extension, User $client, ?Request $request = null): void
    {
        $this->assertClientCanRespond($contract, $extension, $client);

        $this->applyApprovedDate(
            $contract,
            $extension,
            $extension->proposed_delivery_date->copy(),
            $client,
            DeliveryExtensionStatus::Approved,
            'client_accepted',
            $request,
        );
    }

    public function clientDecline(QuestContract $contract, QuestContractDeliveryExtension $extension, User $client, string $reason, ?Request $request = null): void
    {
        $this->assertClientCanRespond($contract, $extension, $client);

        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['decline_reason' => __('Please provide a decline reason (at least 10 characters).')]);
        }

        DB::transaction(function () use ($contract, $extension, $client, $reason, $request): void {
            $extension->update([
                'status' => DeliveryExtensionStatus::Declined,
                'resolution' => 'client_declined',
                'decline_reason' => $reason,
                'resolved_by_user_id' => $client->id,
                'resolved_at' => now(),
            ]);

            $this->clearPendingExtension($contract);
            $this->events->log($contract, 'contract.extension_declined', $client, [
                'extension_id' => $extension->id,
            ], $request);

            $contract->freelancer?->notify(new ContractDeliveryExtensionFreelancerNotification($contract, $extension, 'declined'));
            $this->logFreelancerOutcome($extension, 'declined');
        });
    }

    public function clientCounterPropose(
        QuestContract $contract,
        QuestContractDeliveryExtension $extension,
        User $client,
        string $counterDate,
        ?Request $request = null,
    ): void {
        $this->assertClientCanRespond($contract, $extension, $client);

        $counter = Carbon::parse($counterDate, config('app.timezone'))->startOfDay();
        $original = $extension->original_delivery_date->copy()->startOfDay();
        $proposed = $extension->proposed_delivery_date->copy()->startOfDay();
        $adjustmentType = $extension->adjustment_type ?? DeliveryDateAdjustmentType::Extension;

        if ($adjustmentType === DeliveryDateAdjustmentType::Reduction) {
            if ($counter->lt($proposed) || $counter->gt($original)) {
                throw ValidationException::withMessages([
                    'counter_proposed_date' => __('Pick a date between their earlier date and the current finish date.'),
                ]);
            }
        } elseif ($counter->lte($original) || $counter->gt($proposed)) {
            throw ValidationException::withMessages([
                'counter_proposed_date' => __('Pick a date between the current finish date and the date they asked for.'),
            ]);
        }

        DB::transaction(function () use ($contract, $extension, $client, $counter, $request): void {
            $extension->update([
                'status' => DeliveryExtensionStatus::CounterProposed,
                'counter_proposed_date' => $counter,
                'counter_proposed_at' => now(),
                'counter_response_deadline_at' => now()->addHours(self::COUNTER_RESPONSE_HOURS),
                'resolved_by_user_id' => $client->id,
            ]);

            $this->events->log($contract, 'contract.extension_counter_proposed', $client, [
                'extension_id' => $extension->id,
                'counter_date' => $counter->toDateString(),
            ], $request);

            $contract->freelancer?->notify(new ContractDeliveryExtensionFreelancerNotification($contract, $extension, 'counter_proposed'));
        });
    }

    public function freelancerAcceptCounter(QuestContract $contract, QuestContractDeliveryExtension $extension, User $freelancer, ?Request $request = null): void
    {
        $this->assertFreelancerCanRespondToCounter($contract, $extension, $freelancer);

        $this->applyApprovedDate(
            $contract,
            $extension,
            $extension->counter_proposed_date->copy(),
            $freelancer,
            DeliveryExtensionStatus::Approved,
            'counter_accepted',
            $request,
        );
    }

    public function freelancerDeclineCounter(QuestContract $contract, QuestContractDeliveryExtension $extension, User $freelancer, ?Request $request = null): void
    {
        $this->assertFreelancerCanRespondToCounter($contract, $extension, $freelancer);

        DB::transaction(function () use ($contract, $extension, $freelancer, $request): void {
            $extension->update([
                'status' => DeliveryExtensionStatus::CounterRejected,
                'resolution' => 'counter_declined',
                'resolved_by_user_id' => $freelancer->id,
                'resolved_at' => now(),
            ]);

            $this->clearPendingExtension($contract);
            $this->events->log($contract, 'contract.extension_counter_rejected', $freelancer, [
                'extension_id' => $extension->id,
            ], $request);

            $contract->client?->notify(new ContractDeliveryExtensionClientNotification($contract, $extension, 'counter_rejected'));
            $this->logFreelancerOutcome($extension, 'counter_rejected');
        });
    }

    public function autoApproveClientTimeout(QuestContractDeliveryExtension $extension): void
    {
        if ($extension->status !== DeliveryExtensionStatus::PendingClient) {
            return;
        }

        if (now()->lt($extension->client_response_deadline_at)) {
            return;
        }

        $extension->loadMissing('contract');
        $contract = $extension->contract;
        if ($contract === null) {
            return;
        }

        $this->applyApprovedDate(
            $contract,
            $extension,
            $extension->proposed_delivery_date->copy(),
            null,
            DeliveryExtensionStatus::AutoApproved,
            'client_timeout_auto_approved',
            null,
        );
    }

    public function expireCounterProposal(QuestContractDeliveryExtension $extension): void
    {
        if ($extension->status !== DeliveryExtensionStatus::CounterProposed) {
            return;
        }

        if ($extension->counter_response_deadline_at === null || now()->lt($extension->counter_response_deadline_at)) {
            return;
        }

        $extension->loadMissing('contract');
        $contract = $extension->contract;
        if ($contract === null) {
            return;
        }

        DB::transaction(function () use ($contract, $extension): void {
            $extension->update([
                'status' => DeliveryExtensionStatus::CounterRejected,
                'resolution' => 'counter_expired',
                'resolved_at' => now(),
            ]);

            $this->clearPendingExtension($contract);
            $this->events->log($contract, 'contract.extension_counter_expired', null, [
                'extension_id' => $extension->id,
            ]);

            $contract->freelancer?->notify(new ContractDeliveryExtensionFreelancerNotification($contract, $extension, 'counter_expired'));
            $this->logFreelancerOutcome($extension, 'counter_expired');
        });
    }

    /**
     * @return array{can_request: bool, button_label: string, button_tone: string, reason: ?string, seconds_until_deadline: ?int, extension_count: int, extension_limit: int}
     */
    public function freelancerButtonState(QuestContract $contract): array
    {
        $count = (int) $contract->delivery_extension_count;
        $limit = self::MAX_EXTENSIONS;

        if ($count >= $limit) {
            return [
                'can_request' => false,
                'button_label' => __('No date changes left'),
                'button_tone' => 'disabled',
                'reason' => __('You have used all :max date-change requests on this job.', ['max' => $limit]),
                'seconds_until_deadline' => null,
                'extension_count' => $count,
                'extension_limit' => $limit,
            ];
        }

        if ($contract->status !== ContractStatus::Active) {
            return [
                'can_request' => false,
                'button_label' => __('Change finish date'),
                'button_tone' => 'disabled',
                'reason' => __('Date changes are only available while the job is active.'),
                'seconds_until_deadline' => null,
                'extension_count' => $count,
                'extension_limit' => $limit,
            ];
        }

        if ($contract->pending_extension_id !== null) {
            return [
                'can_request' => false,
                'button_label' => __('Date change pending'),
                'button_tone' => 'disabled',
                'reason' => __('A date-change request is already waiting for a reply.'),
                'seconds_until_deadline' => null,
                'extension_count' => $count,
                'extension_limit' => $limit,
            ];
        }

        $deadline = $contract->agreed_delivery_date;
        if ($deadline === null || now()->gte($deadline->copy()->endOfDay())) {
            return [
                'can_request' => false,
                'button_label' => __('Change finish date'),
                'button_tone' => 'disabled',
                'reason' => __('The finish date has already passed.'),
                'seconds_until_deadline' => null,
                'extension_count' => $count,
                'extension_limit' => $limit,
            ];
        }

        $secondsUntil = (int) max(0, now()->diffInSeconds($deadline->copy()->endOfDay(), false));
        $hoursUntil = $secondsUntil / 3600;
        $tone = $hoursUntil <= self::PROMPT_AMBER_HOURS ? 'amber' : 'default';

        return [
            'can_request' => true,
            'button_label' => __('Change finish date'),
            'button_tone' => $tone,
            'reason' => null,
            'seconds_until_deadline' => $secondsUntil,
            'extension_count' => $count,
            'extension_limit' => $limit,
        ];
    }

    private function applyApprovedDate(
        QuestContract $contract,
        QuestContractDeliveryExtension $extension,
        Carbon $newDate,
        ?User $resolver,
        DeliveryExtensionStatus $status,
        string $resolution,
        ?Request $request,
    ): void {
        DB::transaction(function () use ($contract, $extension, $newDate, $resolver, $status, $resolution, $request): void {
            $amendment = $this->createDeliveryAmendment($contract, $extension, $newDate, $resolver);

            $extension->update([
                'status' => $status,
                'resolution' => $resolution,
                'applied_delivery_date' => $newDate,
                'quest_contract_amendment_id' => $amendment->id,
                'resolved_by_user_id' => $resolver?->id,
                'resolved_at' => now(),
            ]);

            $this->applyDeliveryDateToContract($contract, $extension, $newDate);
            $this->clearPendingExtension($contract);

            $eventType = $status === DeliveryExtensionStatus::AutoApproved
                ? 'contract.extension_auto_approved'
                : 'contract.extension_approved';

            $this->events->log($contract, $eventType, $resolver, [
                'extension_id' => $extension->id,
                'applied_date' => $newDate->toDateString(),
                'amendment_id' => $amendment->id,
            ], $request);

            $contract->client?->notify(new ContractDeliveryExtensionClientNotification(
                $contract,
                $extension->fresh(),
                $status === DeliveryExtensionStatus::AutoApproved ? 'auto_approved' : 'approved',
            ));
            $contract->freelancer?->notify(new ContractDeliveryExtensionFreelancerNotification($contract, $extension->fresh(), 'approved'));
            $this->logFreelancerOutcome($extension, $resolution);
        });
    }

    private function createDeliveryAmendment(
        QuestContract $contract,
        QuestContractDeliveryExtension $extension,
        Carbon $newDate,
        ?User $resolver,
    ): QuestContractAmendment {
        $terms = $contract->effectiveTerms();
        $originalLabel = $terms['timeline']['agreed_delivery_label'] ?? $extension->original_delivery_date->format('j M Y');

        return QuestContractAmendment::query()->create([
            'quest_contract_id' => $contract->id,
            'amendment_number' => $contract->amendment_count + 1,
            'requested_by_user_id' => $extension->requested_by_user_id,
            'amendment_type' => ContractAmendmentType::DeliveryDate,
            'description' => __('Delivery extension #:num — :reason', [
                'num' => $extension->extension_number,
                'reason' => $extension->reason_category->label(),
            ]),
            'reason' => $extension->explanation,
            'original_value' => $originalLabel,
            'new_value' => $newDate->toDateString(),
            'status' => 'accepted',
            'responded_by_user_id' => $resolver?->id,
            'responded_at' => now(),
            'applied_terms_delta' => [
                'timeline' => [
                    'agreed_delivery_date' => $newDate->toDateString(),
                    'agreed_delivery_label' => $newDate->format('j M Y'),
                ],
            ],
        ]);
    }

    private function applyDeliveryDateToContract(
        QuestContract $contract,
        QuestContractDeliveryExtension $extension,
        Carbon $newDate,
    ): void {
        $current = $contract->current_terms_snapshot ?? [];
        $extensions = $current['timeline']['extensions'] ?? [];

        $extensions[] = [
            'extension_number' => $extension->extension_number,
            'original_date' => $extension->original_delivery_date->toDateString(),
            'original_label' => $extension->original_delivery_date->format('j M Y'),
            'new_date' => $newDate->toDateString(),
            'new_label' => $newDate->format('j M Y'),
            'reason_category' => $extension->reason_category->value,
            'reason_label' => $extension->reason_category->label(),
            'resolution' => $extension->resolution,
            'approved_at' => now()->toIso8601String(),
        ];

        $timelineDelta = [
            'agreed_delivery_date' => $newDate->toDateString(),
            'agreed_delivery_label' => $newDate->format('j M Y'),
            'extensions' => $extensions,
        ];

        $contract->update([
            'agreed_delivery_date' => $newDate,
            'amendment_count' => $contract->amendment_count + 1,
            'delivery_extension_count' => $contract->delivery_extension_count + 1,
            'current_terms_snapshot' => array_replace_recursive($current, ['timeline' => $timelineDelta]),
        ]);

        $quest = $contract->quest;
        if ($quest !== null) {
            $updates = [
                'due_at' => $newDate->copy()->endOfDay(),
                'estimated_delivery_date' => $newDate->toDateString(),
            ];
            if ($quest->delivery_deadline !== null) {
                $updates['delivery_deadline'] = $newDate->toDateString();
            }
            $quest->update($updates);
        }
    }

    private function clearPendingExtension(QuestContract $contract): void
    {
        $contract->update([
            'pending_extension_id' => null,
            'deadline_clock_paused_at' => null,
        ]);
    }

    private function assertFreelancerCanRequest(QuestContract $contract, User $freelancer): void
    {
        if ((int) $contract->freelancer_id !== (int) $freelancer->id) {
            abort(403);
        }

        if ($contract->delivery_extension_count >= self::MAX_DATE_ADJUSTMENTS) {
            throw ValidationException::withMessages(['contract' => __('You have used all :max date-change requests on this job.', ['max' => self::MAX_DATE_ADJUSTMENTS])]);
        }

        if ($contract->pending_extension_id !== null) {
            throw ValidationException::withMessages(['contract' => __('A date-change request is already waiting for a reply.')]);
        }

        if ($contract->status !== ContractStatus::Active) {
            throw ValidationException::withMessages(['contract' => __('Date changes are only available while the job is active.')]);
        }
    }

    private function assertProposedDate(Carbon $originalDate, Carbon $proposed, DeliveryDateAdjustmentType $type): void
    {
        $today = now()->startOfDay();

        if ($proposed->lt($today)) {
            throw ValidationException::withMessages(['proposed_delivery_date' => __('The new finish date cannot be in the past.')]);
        }

        if ($type === DeliveryDateAdjustmentType::Extension) {
            if ($proposed->lte($originalDate)) {
                throw ValidationException::withMessages(['proposed_delivery_date' => __('To ask for more time, pick a date after the current finish date.')]);
            }

            $maxDate = $originalDate->copy()->addDays(self::MAX_EXTENSION_DAYS);
            if ($proposed->gt($maxDate)) {
                throw ValidationException::withMessages([
                    'proposed_delivery_date' => __('You can only extend up to :days extra days at a time.', ['days' => self::MAX_EXTENSION_DAYS]),
                ]);
            }

            return;
        }

        if ($proposed->gte($originalDate)) {
            throw ValidationException::withMessages(['proposed_delivery_date' => __('To finish sooner, pick a date before the current finish date.')]);
        }

        $minDate = $originalDate->copy()->subDays(self::MAX_REDUCTION_DAYS);
        if ($proposed->lt($minDate)) {
            throw ValidationException::withMessages([
                'proposed_delivery_date' => __('You can only move the finish date up to :days days earlier at a time.', ['days' => self::MAX_REDUCTION_DAYS]),
            ]);
        }
    }

    private function assertClientCanRespond(QuestContract $contract, QuestContractDeliveryExtension $extension, User $client): void
    {
        if ((int) $contract->client_id !== (int) $client->id) {
            abort(403);
        }

        if ((int) $extension->quest_contract_id !== (int) $contract->id) {
            abort(404);
        }

        if ($extension->status !== DeliveryExtensionStatus::PendingClient) {
            throw ValidationException::withMessages(['extension' => __('This extension is not awaiting your response.')]);
        }
    }

    private function assertFreelancerCanRespondToCounter(QuestContract $contract, QuestContractDeliveryExtension $extension, User $freelancer): void
    {
        if ((int) $contract->freelancer_id !== (int) $freelancer->id) {
            abort(403);
        }

        if ($extension->status !== DeliveryExtensionStatus::CounterProposed) {
            throw ValidationException::withMessages(['extension' => __('No counter-proposal is awaiting your response.')]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, name: string, url: string}>
     */
    private function storeProgressAttachments(QuestContract $contract, array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store("contracts/{$contract->reference_code}/extensions", 'public');
            $stored[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'url' => Storage::disk('public')->url($path),
            ];
        }

        return $stored;
    }

    private function logFreelancerOutcome(QuestContractDeliveryExtension $extension, string $outcome): void
    {
        FreelancerDeliveryExtensionLog::query()->create([
            'user_id' => $extension->requested_by_user_id,
            'quest_contract_id' => $extension->quest_contract_id,
            'delivery_extension_id' => $extension->id,
            'outcome' => $outcome,
            'reason_category' => $extension->reason_category->value,
            'logged_at' => now(),
        ]);
    }
}
