<?php

namespace App\Services\Quest;

use App\Enums\ContractStatus;
use App\Models\Quest;
use App\Models\QuestContract;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Single source of truth for quest completion dates.
 *
 * Terminology:
 * - Planned finish (`estimated_delivery_date`): client's soft target — "I hope it is done by then."
 * - Delivery deadline (`delivery_deadline`): client's hard cutoff — work must be finished by then.
 * - Engagement anchor: date that drives mid-check-in emails, post-deadline reminders, and auto-release.
 *
 * Backward compatibility: quests with only `estimated_delivery_date` or legacy `due_at` keep working.
 */
final class QuestCompletionScheduleService
{
    public function plannedFinishDate(Quest $quest): ?CarbonInterface
    {
        if ($quest->estimated_delivery_date === null) {
            return null;
        }

        return $this->endOfAppDay($quest->estimated_delivery_date);
    }

    public function hardDeadlineDate(Quest $quest): ?CarbonInterface
    {
        if ($quest->delivery_deadline !== null) {
            return $this->endOfAppDay($quest->delivery_deadline);
        }

        $contract = $this->activeContract($quest);
        if ($contract?->agreed_delivery_date !== null) {
            return $this->endOfAppDay($contract->agreed_delivery_date);
        }

        return null;
    }

    /**
     * Date used for escrow engagement emails and auto-release countdown.
     */
    public function engagementAnchorAt(Quest $quest): ?CarbonInterface
    {
        $recurring = app(QuestRecurringEngagementService::class);
        if ($recurring->isRecurring($quest)) {
            $installment = $recurring->currentInstallment($quest);
            if ($installment !== null) {
                return $installment->period_end_at->copy()->timezone($this->timezone());
            }

            if ($quest->contract_ends_at !== null) {
                return $quest->contract_ends_at->copy()->timezone($this->timezone());
            }
        }

        $contract = $this->activeContract($quest);
        if ($contract?->agreed_delivery_date !== null) {
            return $this->endOfAppDay($contract->agreed_delivery_date);
        }

        if ($quest->delivery_deadline !== null) {
            return $this->endOfAppDay($quest->delivery_deadline);
        }

        $quest->loadMissing('acceptedOffer');
        if ($quest->acceptedOffer?->planned_finish_date !== null
            && $this->questIsEngaged($quest)) {
            return $this->endOfAppDay($quest->acceptedOffer->planned_finish_date);
        }

        if ($quest->estimated_delivery_date !== null) {
            return $this->endOfAppDay($quest->estimated_delivery_date);
        }

        if ($quest->due_at !== null) {
            return $quest->due_at->copy()->timezone($this->timezone());
        }

        if ($quest->escrow_funded_at !== null && (int) ($quest->estimated_completion_days ?? 0) > 0) {
            return $quest->escrow_funded_at->copy()->addDays((int) $quest->estimated_completion_days);
        }

        return null;
    }

    /**
     * Nearest future hard date for matching urgency (deadline-first).
     */
    public function urgencyAnchorAt(Quest $quest): ?CarbonInterface
    {
        return $this->hardDeadlineDate($quest)
            ?? $this->plannedFinishDate($quest)
            ?? ($quest->due_at?->copy()->timezone($this->timezone()));
    }

    /**
     * Resolve initial `due_at` when a quest is created or reposted.
     *
     * @param  array<string, mixed>  $data
     */
    public function initialDueAtFromCreateData(array $data): Carbon
    {
        if (! empty($data['delivery_deadline'])) {
            return $this->endOfAppDay($data['delivery_deadline']);
        }

        if (! empty($data['estimated_delivery_date'])) {
            return $this->endOfAppDay($data['estimated_delivery_date']);
        }

        return now()->addDays(max(1, (int) ($data['estimated_completion_days'] ?? 14)));
    }

    /**
     * Frontend/admin payload with consistent labels.
     *
     * @return array<string, mixed>
     */
    public function toPayload(Quest $quest): array
    {
        $planned = $this->plannedFinishDate($quest);
        $hard = $this->hardDeadlineDate($quest);
        $anchor = $this->engagementAnchorAt($quest);
        $anchorRole = $this->engagementAnchorRole($quest, $anchor);

        return [
            'planned_finish_date' => $planned?->toDateString(),
            'planned_finish_label' => __('Planned finish'),
            'hard_deadline_date' => $hard?->toDateString(),
            'hard_deadline_label' => __('Delivery deadline'),
            'engagement_anchor_date' => $anchor?->toDateString(),
            'engagement_anchor_at' => $anchor?->timezone($this->timezone())->toIso8601String(),
            'engagement_anchor_role' => $anchorRole,
            'engagement_anchor_label' => $this->engagementAnchorLabel($anchorRole),
            'has_planned_finish' => $planned !== null,
            'has_hard_deadline' => $hard !== null,
            'summary' => $this->summaryLine($planned, $hard),
        ];
    }

    private function engagementAnchorRole(Quest $quest, ?CarbonInterface $anchor): ?string
    {
        if ($anchor === null) {
            return null;
        }

        $contract = $this->activeContract($quest);
        if ($contract?->agreed_delivery_date !== null
            && $this->endOfAppDay($contract->agreed_delivery_date)?->toDateString() === $anchor->toDateString()) {
            return 'contract';
        }

        if ($quest->delivery_deadline !== null
            && $this->endOfAppDay($quest->delivery_deadline)?->toDateString() === $anchor->toDateString()) {
            return 'hard_deadline';
        }

        $quest->loadMissing('acceptedOffer');
        if ($quest->acceptedOffer?->planned_finish_date !== null
            && $this->endOfAppDay($quest->acceptedOffer->planned_finish_date)?->toDateString() === $anchor->toDateString()) {
            return 'proposal_finish';
        }

        if ($quest->estimated_delivery_date !== null
            && $this->endOfAppDay($quest->estimated_delivery_date)?->toDateString() === $anchor->toDateString()) {
            return 'planned_finish';
        }

        if ($quest->due_at !== null && $quest->due_at->toDateString() === $anchor->toDateString()) {
            return 'legacy_due_at';
        }

        if ($quest->escrow_funded_at !== null) {
            return 'duration_fallback';
        }

        return 'other';
    }

    private function engagementAnchorLabel(?string $role): ?string
    {
        return match ($role) {
            'contract' => __('Contract delivery date'),
            'hard_deadline' => __('Delivery deadline'),
            'proposal_finish' => __('Accepted proposal finish date'),
            'planned_finish' => __('Planned finish'),
            'legacy_due_at' => __('Estimated completion'),
            'duration_fallback' => __('Duration from escrow funding'),
            default => null,
        };
    }

    private function summaryLine(?CarbonInterface $planned, ?CarbonInterface $hard): ?string
    {
        if ($planned !== null && $hard !== null) {
            if ($planned->toDateString() === $hard->toDateString()) {
                return __('Finish target & deadline: :date', [
                    'date' => $planned->timezone($this->timezone())->format('j M Y'),
                ]);
            }

            return __('Target finish :finish · Hard deadline :deadline', [
                'finish' => $planned->timezone($this->timezone())->format('j M Y'),
                'deadline' => $hard->timezone($this->timezone())->format('j M Y'),
            ]);
        }

        if ($hard !== null) {
            return __('Delivery deadline: :date', [
                'date' => $hard->timezone($this->timezone())->format('j M Y'),
            ]);
        }

        if ($planned !== null) {
            return __('Planned finish: :date', [
                'date' => $planned->timezone($this->timezone())->format('j M Y'),
            ]);
        }

        return null;
    }

    private function activeContract(Quest $quest): ?QuestContract
    {
        if ($quest->accepted_quest_offer_id === null) {
            return null;
        }

        return QuestContract::query()
            ->where('quest_id', $quest->id)
            ->where('quest_offer_id', $quest->accepted_quest_offer_id)
            ->whereIn('status', [
                ContractStatus::Active->value,
                ContractStatus::AmendmentPending->value,
                ContractStatus::PendingEscrow->value,
            ])
            ->latest('id')
            ->first();
    }

    private function questIsEngaged(Quest $quest): bool
    {
        return in_array($quest->status?->value ?? (string) $quest->status, [
            'in_progress',
            'completed',
            'disputed',
        ], true) || $quest->escrow_funded_at !== null;
    }

    /**
     * @param  CarbonInterface|string|null  $value
     */
    private function endOfAppDay(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, $this->timezone())->endOfDay();
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Africa/Lagos');
    }
}
