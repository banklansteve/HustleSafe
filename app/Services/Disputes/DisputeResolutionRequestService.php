<?php

namespace App\Services\Disputes;

use App\Enums\DisputeResolutionOption;
use App\Enums\QuestDisputeManagementStatus;
use App\Models\DisputeResolutionRequest;
use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\QuestDisputeUpdatedNotification;
use App\Services\Disputes\DisputeStaffAlertService;
use App\Services\Disputes\DisputeSuperAdminAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeResolutionRequestService
{
    public function __construct(
        private readonly DisputeResolutionMatrixService $matrix,
        private readonly QuestDisputeWorkflowService $workflow,
    ) {}

    /**
     * @param  array{option: string, client_share_percent?: int|null, extend_days?: int|null, revision_days?: int|null, target_completion_date?: string|null, terms_note?: string|null}  $data
     */
    public function propose(User $actor, QuestDispute $dispute, array $data): DisputeResolutionRequest
    {
        $quest = $dispute->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['dispute' => [__('Quest not found.')]]);
        }

        $partyRole = $this->workflow->partyFor($actor, $quest);
        if ($partyRole === null || ! $dispute->isParty($actor)) {
            throw ValidationException::withMessages(['dispute' => [__('You are not a party on this dispute.')]]);
        }

        if (! $dispute->isOpen()) {
            throw ValidationException::withMessages(['dispute' => [__('This dispute is already closed.')]]);
        }

        $option = (string) $data['option'];
        $this->matrix->assertActorCanUse($partyRole, $option);

        $enum = DisputeResolutionOption::from($option);
        $terms = $this->normalizeTerms($enum, $data);

        if ($enum === DisputeResolutionOption::SplitFund) {
            return $this->proposeSplitViaSettlement($actor, $dispute, $terms);
        }

        return DB::transaction(function () use ($actor, $dispute, $partyRole, $option, $terms, $enum): DisputeResolutionRequest {
            $this->supersedePending($dispute, $actor);

            $request = DisputeResolutionRequest::query()->create([
                'quest_dispute_id' => $dispute->id,
                'requested_by_user_id' => $actor->id,
                'party_role' => $partyRole,
                'option' => $option,
                'terms' => $terms,
                'status' => 'pending',
            ]);

            $this->tryMatchMutual($request);

            $request->refresh();

            $this->workflow->log($dispute, $actor, 'dispute.resolution_proposed', [
                'option' => $option,
                'option_label' => $enum->label(),
                'status' => $request->status,
                'party_role' => $partyRole,
            ]);

            if ($request->status === 'matched') {
                $dispute->forceFill([
                    'management_status' => QuestDisputeManagementStatus::ReadyForDecision,
                    'ready_for_decision_at' => now(),
                ])->save();

                $staff = $dispute->assignedStaff;
                if ($staff !== null) {
                    app(DisputeSuperAdminAlertService::class)->notifyReadyForDecision($dispute->fresh(), $staff);
                    app(DisputeStaffAlertService::class)->notifyPartiesAgreedOnProposal($dispute->fresh(), $staff, $enum);
                }

                foreach (array_filter([$dispute->quest?->client, $dispute->quest?->freelancer]) as $party) {
                    $party?->notify(new QuestDisputeUpdatedNotification(
                        $dispute,
                        __('Both parties agreed'),
                        __('You both proposed the same outcome (:option). Customer Support will review and close the case.', [
                            'option' => $enum->label(),
                        ]),
                    ));
                }
            } elseif ($this->matrix->resolutionPath($enum) === 'support') {
                $staff = $dispute->assignedStaff;
                if ($staff !== null) {
                    app(DisputeStaffAlertService::class)->notifyResolutionProposed($dispute, $staff, $actor, $enum);
                }
            }

            $other = $dispute->quest?->oppositeParty($actor);
            if ($other !== null) {
                $other->notify(new QuestDisputeUpdatedNotification(
                    $dispute,
                    __('New resolution proposal'),
                    __(':name proposed: :option. Review it on the dispute page.', [
                        'name' => $actor->first_name ?: $actor->name,
                        'option' => $enum->label(),
                    ]),
                ));
            }

            return $request;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForDispute(QuestDispute $dispute): array
    {
        return $dispute->resolutionRequests()
            ->with('requestedBy:id,name,first_name')
            ->latest('id')
            ->get()
            ->map(fn (DisputeResolutionRequest $row) => [
                'id' => $row->id,
                'option' => $row->option,
                'option_label' => $row->optionEnum()?->label() ?? $row->option,
                'party_role' => $row->party_role,
                'terms' => $row->terms ?? [],
                'status' => $row->status,
                'requested_by' => [
                    'id' => $row->requestedBy?->id,
                    'name' => $row->requestedBy?->name,
                ],
                'created_at' => $row->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->values()
            ->all();
    }

  /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeTermsForNegotiation(DisputeResolutionOption $option, array $data): array
    {
        return $this->normalizeTerms($option, $data);
    }

  /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeTerms(DisputeResolutionOption $option, array $data): array
    {
        $terms = [
            'note' => isset($data['terms_note']) ? trim((string) $data['terms_note']) : null,
        ];

        if ($option->requiresClientShare()) {
            $share = (int) ($data['client_share_percent'] ?? $option->defaultClientSharePercent() ?? 50);
            if ($share < 0 || $share > 100) {
                throw ValidationException::withMessages(['client_share_percent' => [__('Client share must be between 0 and 100.')]]);
            }
            $terms['client_share_percent'] = $share;
        }

        if ($option->requiresDays()) {
            $days = (int) ($data['extend_days'] ?? 7);
            if ($days < 1 || $days > 90) {
                throw ValidationException::withMessages(['extend_days' => [__('Extension must be between 1 and 90 days.')]]);
            }
            $terms['extend_days'] = $days;
        }

        if ($option->optionalRevisionDays() && isset($data['revision_days']) && $data['revision_days'] !== null && $data['revision_days'] !== '') {
            $revisionDays = (int) $data['revision_days'];
            if ($revisionDays < 1 || $revisionDays > 90) {
                throw ValidationException::withMessages(['revision_days' => [__('Revision window must be between 1 and 90 days.')]]);
            }
            $terms['revision_days'] = $revisionDays;
        }

        if ($option->requiresTargetDate()) {
            $targetDate = isset($data['target_completion_date']) ? trim((string) $data['target_completion_date']) : null;
            if ($targetDate === null || $targetDate === '') {
                throw ValidationException::withMessages(['target_completion_date' => [__('Please pick the new completion date you both agree on.')]]);
            }
            $terms['target_completion_date'] = $targetDate;
        }

        if ($option->requiresTermsNote() && empty($terms['note'])) {
            throw ValidationException::withMessages(['terms_note' => [__('Please explain the agreement in plain language.')]]);
        }

        if ($option === DisputeResolutionOption::Other && mb_strlen((string) ($terms['note'] ?? '')) < 10) {
            throw ValidationException::withMessages(['terms_note' => [__('Please describe the agreement clearly (at least 10 characters).')]]);
        }

        if (! $option->requiresTermsNote() && empty($terms['note']) && ! in_array($option, [
            DisputeResolutionOption::SplitFund,
            DisputeResolutionOption::RefundCancel,
        ], true)) {
            throw ValidationException::withMessages(['terms_note' => [__('Please explain why you are asking for this outcome.')]]);
        }

        return array_filter($terms, fn ($v) => $v !== null && $v !== '');
    }

    private function proposeSplitViaSettlement(User $actor, QuestDispute $dispute, array $terms): DisputeResolutionRequest
    {
        $share = (int) ($terms['client_share_percent'] ?? 50);
        $note = $terms['note'] ?? null;
        $this->workflow->submitSettlementOffer($actor, $dispute, $share, $note);

        return DisputeResolutionRequest::query()->create([
            'quest_dispute_id' => $dispute->id,
            'requested_by_user_id' => $actor->id,
            'party_role' => $this->workflow->partyFor($actor, $dispute->quest) ?? 'party',
            'option' => DisputeResolutionOption::SplitFund->value,
            'terms' => $terms,
            'status' => 'settlement_offered',
        ]);
    }

    private function supersedePending(QuestDispute $dispute, User $actor): void
    {
        DisputeResolutionRequest::query()
            ->where('quest_dispute_id', $dispute->id)
            ->where('requested_by_user_id', $actor->id)
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);
    }

    private function tryMatchMutual(DisputeResolutionRequest $request): void
    {
        $enum = $request->optionEnum();
        if ($enum === null || ! $enum->isMutual()) {
            return;
        }

        $counterpart = DisputeResolutionRequest::query()
            ->where('quest_dispute_id', $request->quest_dispute_id)
            ->where('option', $request->option)
            ->where('status', 'pending')
            ->where('requested_by_user_id', '!=', $request->requested_by_user_id)
            ->latest('id')
            ->first();

        if ($counterpart === null) {
            return;
        }

        if (! $this->termsMatch($request->terms ?? [], $counterpart->terms ?? [], $enum)) {
            return;
        }

        $request->update(['status' => 'matched', 'matched_request_id' => $counterpart->id]);
        $counterpart->update(['status' => 'matched', 'matched_request_id' => $request->id]);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function termsMatch(array $a, array $b, DisputeResolutionOption $option): bool
    {
        return match ($option) {
            DisputeResolutionOption::RefundCancel => true,
            DisputeResolutionOption::SplitFund => (int) ($a['client_share_percent'] ?? -1) === (int) ($b['client_share_percent'] ?? -2),
            DisputeResolutionOption::ExtendDelivery => (int) ($a['extend_days'] ?? -1) === (int) ($b['extend_days'] ?? -2)
                && $this->notesMatch($a, $b, strict: false),
            DisputeResolutionOption::ReviseRedo => $this->revisionTermsMatch($a, $b),
            DisputeResolutionOption::AdjustTimeline => $this->timelineTermsMatch($a, $b),
            DisputeResolutionOption::ScopeAdjustment,
            DisputeResolutionOption::Other,
            DisputeResolutionOption::CustomSettlement => $this->notesMatch($a, $b),
            default => $this->notesMatch($a, $b),
        };
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function revisionTermsMatch(array $a, array $b): bool
    {
        $daysA = $a['revision_days'] ?? null;
        $daysB = $b['revision_days'] ?? null;

        if ($daysA !== null && $daysB !== null && (int) $daysA !== (int) $daysB) {
            return false;
        }

        return $this->notesMatch($a, $b);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function timelineTermsMatch(array $a, array $b): bool
    {
        $dateA = $a['target_completion_date'] ?? null;
        $dateB = $b['target_completion_date'] ?? null;

        if ($dateA !== null && $dateB !== null) {
            return (string) $dateA === (string) $dateB;
        }

        return $this->notesMatch($a, $b);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function notesMatch(array $a, array $b, bool $strict = true): bool
    {
        $noteA = $this->normalizeNote((string) ($a['note'] ?? ''));
        $noteB = $this->normalizeNote((string) ($b['note'] ?? ''));

        if ($noteA === '' || $noteB === '') {
            return ! $strict;
        }

        return $noteA === $noteB;
    }

    private function normalizeNote(string $note): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($note)) ?? trim($note);

        return mb_strtolower($collapsed);
    }
}
