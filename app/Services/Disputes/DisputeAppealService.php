<?php

namespace App\Services\Disputes;

use App\Enums\DisputeNegotiationPhase;
use App\Enums\DisputeResolutionOption;
use App\Enums\QuestDisputeManagementStatus;
use App\Models\DisputeAppeal;
use App\Models\DisputeEvent;
use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\QuestDisputeUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeAppealService
{
    public function __construct(
        private readonly QuestDisputeWorkflowService $workflow,
        private readonly DisputeResolutionMatrixService $matrix,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function fileAppeal(User $actor, QuestDispute $dispute, array $data): DisputeAppeal
    {
        $partyRole = $this->assertParty($actor, $dispute);
        $this->assertCanFile($dispute, $actor);

        $option = isset($data['proposed_option'])
            ? DisputeResolutionOption::from((string) $data['proposed_option'])
            : null;

        if ($option !== null) {
            $this->matrix->assertActorCanUse($partyRole, $option->value);
        }

        $terms = $option !== null
            ? app(DisputeResolutionRequestService::class)->normalizeTermsForNegotiation($option, $data)
            : ($data['proposed_terms'] ?? null);

        return DB::transaction(function () use ($actor, $dispute, $partyRole, $data, $option, $terms): DisputeAppeal {
            $appeal = DisputeAppeal::query()->create([
                'quest_dispute_id' => $dispute->id,
                'filed_by_user_id' => $actor->id,
                'party_role' => $partyRole,
                'unfair_reason' => (string) $data['unfair_reason'],
                'proposed_option' => $option?->value,
                'proposed_terms' => $terms,
                'status' => 'filed',
            ]);

            $dispute->forceFill([
                'negotiation_phase' => DisputeNegotiationPhase::AppealUnderReview->value,
                'management_status' => QuestDisputeManagementStatus::Mediation,
                'appeals_used' => (int) $dispute->appeals_used + 1,
            ])->save();

            $this->recordEvent($dispute, $actor, 'appeal.filed', [
                'appeal_id' => $appeal->id,
                'proposed_option' => $option?->value,
            ]);

            $this->notifyAppealFiled($dispute->fresh(), $actor, $appeal);

            return $appeal;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function respondToAppeal(User $actor, DisputeAppeal $appeal, array $data): DisputeAppeal
    {
        $dispute = $appeal->dispute;
        $this->assertParty($actor, $dispute);

        if ((int) $appeal->filed_by_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['appeal' => __('You cannot respond to your own appeal.')]);
        }

        if (! in_array($appeal->status, ['filed', 'counter_pending'], true)) {
            throw ValidationException::withMessages(['appeal' => __('This appeal is no longer accepting responses.')]);
        }

        $appeal->update([
            'counter_response' => $data['counter_response'] ?? null,
            'counter_by_user_id' => $actor->id,
            'counter_responded_at' => now(),
            'status' => 'under_review',
        ]);

        $this->recordEvent($dispute, $actor, 'appeal.counter_response', ['appeal_id' => $appeal->id]);

        app(DisputeSuperAdminAlertService::class)->notifyAppealUnderReview($dispute->fresh(), $appeal->loadMissing('filedBy'));

        return $appeal->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForParty(QuestDispute $dispute, User $viewer): array
    {
        $role = $this->workflow->partyFor($viewer, $dispute->quest);
        $openAppeal = $dispute->appeals()
            ->whereIn('status', ['filed', 'counter_pending', 'under_review'])
            ->latest('id')
            ->first();

        $canFile = $this->canFileAppeal($dispute, $viewer);
        $canRespond = $openAppeal
            && (int) $openAppeal->filed_by_user_id !== (int) $viewer->id
            && in_array($openAppeal->status, ['filed', 'counter_pending'], true)
            && $dispute->isParty($viewer);

        return [
            'can_file' => $canFile,
            'can_respond' => $canRespond,
            'rejection_window_ends_at' => $dispute->rejection_window_ends_at?->timezone('Africa/Lagos')->toIso8601String(),
            'appeal_window_ends_at' => $dispute->appeal_window_ends_at?->timezone('Africa/Lagos')->toIso8601String(),
            'open_appeal' => $openAppeal ? [
                'id' => $openAppeal->id,
                'status' => $openAppeal->status,
                'unfair_reason' => $openAppeal->unfair_reason,
                'proposed_option' => $openAppeal->proposed_option,
                'proposed_terms' => $openAppeal->proposed_terms ?? [],
                'filed_by' => $openAppeal->filedBy?->name,
                'counter_response' => $openAppeal->counter_response,
                'created_at' => $openAppeal->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'respond_url' => route('disputes.appeals.respond', ['dispute' => $dispute, 'appeal' => $openAppeal->id]),
            ] : null,
            'history' => $dispute->appeals()
                ->latest('id')
                ->get()
                ->map(fn (DisputeAppeal $a) => [
                    'id' => $a->id,
                    'status' => $a->status,
                    'upheld_original' => $a->upheld_original,
                    'review_outcome_notes' => $a->review_outcome_notes,
                    'created_at' => $a->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ])
                ->all(),
            'viewer_role' => $role,
            'is_enforcement_window' => $dispute->management_status === QuestDisputeManagementStatus::AwaitingEnforcement,
            'is_mutual_appeal_window' => $dispute->resolution_outcome === 'mutual_negotiation_approved'
                && $dispute->appeal_window_ends_at !== null
                && now()->lessThan($dispute->appeal_window_ends_at),
        ];
    }

    protected function canFileAppeal(QuestDispute $dispute, User $viewer): bool
    {
        if (! $dispute->isParty($viewer)) {
            return false;
        }

        if ($dispute->appeals()->whereIn('status', ['filed', 'counter_pending', 'under_review'])->exists()) {
            return false;
        }

        if ((int) $dispute->appeals_used >= (int) config('disputes.max_appeals_per_dispute', 1)) {
            return false;
        }

        if ($dispute->management_status === QuestDisputeManagementStatus::AwaitingEnforcement
            && $dispute->rejection_window_ends_at !== null
            && now()->lessThan($dispute->rejection_window_ends_at)) {
            return true;
        }

        if ($dispute->resolution_outcome === 'mutual_negotiation_approved'
            && $dispute->appeal_window_ends_at !== null
            && now()->lessThan($dispute->appeal_window_ends_at)) {
            return true;
        }

        return false;
    }

    protected function assertCanFile(QuestDispute $dispute, User $actor): void
    {
        if (! $this->canFileAppeal($dispute, $actor)) {
            throw ValidationException::withMessages(['appeal' => __('You cannot file an appeal on this dispute right now.')]);
        }
    }

    protected function assertParty(User $actor, QuestDispute $dispute): string
    {
        $quest = $dispute->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['dispute' => __('Quest not found.')]);
        }

        $role = $this->workflow->partyFor($actor, $quest);
        if ($role === null || ! $dispute->isParty($actor)) {
            throw ValidationException::withMessages(['dispute' => __('You are not a party on this dispute.')]);
        }

        return $role;
    }

    protected function notifyAppealFiled(QuestDispute $dispute, User $filer, DisputeAppeal $appeal): void
    {
        $dispute->loadMissing(['quest.client', 'quest.freelancer']);
        $other = $dispute->quest?->oppositeParty($filer);
        $hours = (int) config('disputes.negotiation.appeal_response_hours', 24);

        if ($other !== null) {
            $other->notify(new QuestDisputeUpdatedNotification(
                $dispute,
                __('Appeal filed on dispute'),
                __(':name filed an appeal. You may respond within :hours hours.', [
                    'name' => $filer->first_name ?: $filer->name,
                    'hours' => $hours,
                ]),
                __('The final decision after appeal review will be binding with no further appeals.'),
                __('View dispute'),
                'both',
            ));
        }

        app(DisputeSuperAdminAlertService::class)->notifyAppealUnderReview($dispute->fresh(), $appeal->loadMissing('filedBy'));
    }

    private function recordEvent(QuestDispute $dispute, ?User $actor, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
