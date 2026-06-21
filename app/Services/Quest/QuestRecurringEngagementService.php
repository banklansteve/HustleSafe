<?php

namespace App\Services\Quest;

use App\Enums\QuestEngagementMode;
use App\Enums\QuestInstallmentFrequency;
use App\Enums\QuestInstallmentStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestDeliverySubmission;
use App\Models\QuestPaymentInstallment;
use App\Models\User;
use App\Support\EscrowAutoReleasePolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QuestRecurringEngagementService
{
    public function isRecurring(Quest $quest): bool
    {
        $mode = $quest->engagement_mode;

        if ($mode instanceof QuestEngagementMode) {
            return $mode === QuestEngagementMode::RecurringInstallment;
        }

        return (string) $mode === QuestEngagementMode::RecurringInstallment->value;
    }

    public function categoryEligible(?QuestCategory $leaf): bool
    {
        if ($leaf === null) {
            return false;
        }

        $leaf->loadMissing('parent');
        $parentSlug = $leaf->parent?->slug ?? $leaf->slug;

        return in_array($parentSlug, config('recurring_engagement.eligible_parent_slugs', []), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(?QuestCategory $leaf): array
    {
        $eligible = $this->categoryEligible($leaf);

        return [
            'eligible' => $eligible,
            'first_period_days' => (int) config('recurring_engagement.first_period_days', 7),
            'contract_duration_options' => collect(config('recurring_engagement.contract_duration_options', []))
                ->map(fn ($label, $months) => ['value' => (int) $months, 'label' => __($label)])
                ->values()
                ->all(),
            'frequencies' => QuestInstallmentFrequency::options(),
            'explainer' => __('For ongoing work like tutoring, you pay the full amount into escrow upfront. The worker is paid in weekly or monthly slices after each period — not all at once at the end.'),
            'renewal_explainer' => $eligible
                ? app(QuestContractRenewalService::class)->createExplainerPayload()
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertCreateDataValid(array $data, ?QuestCategory $leaf): void
    {
        $mode = QuestEngagementMode::tryFrom((string) ($data['engagement_mode'] ?? QuestEngagementMode::OneTime->value))
            ?? QuestEngagementMode::OneTime;

        if ($mode !== QuestEngagementMode::RecurringInstallment) {
            return;
        }

        if (! $this->categoryEligible($leaf)) {
            throw ValidationException::withMessages([
                'engagement_mode' => [__('Installment payment is not available for this category.')],
            ]);
        }

        $months = (int) ($data['contract_duration_months'] ?? 0);
        $allowed = array_keys(config('recurring_engagement.contract_duration_options', []));
        if (! in_array($months, $allowed, true)) {
            throw ValidationException::withMessages([
                'contract_duration_months' => [__('Choose how long the contract runs.')],
            ]);
        }

        $frequency = QuestInstallmentFrequency::tryFrom((string) ($data['installment_frequency'] ?? ''));
        if ($frequency === null) {
            throw ValidationException::withMessages([
                'installment_frequency' => [__('Choose how often the worker gets paid.')],
            ]);
        }

        $preference = (string) ($data['contract_renewal_preference'] ?? '');
        if (! in_array($preference, ['extend', 'continue', 'republish', 'decide_later'], true)) {
            throw ValidationException::withMessages([
                'contract_renewal_preference' => [__('Choose what should happen when the contract ends.')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeCreateAttributes(array $data, ?QuestCategory $leaf): array
    {
        $mode = QuestEngagementMode::tryFrom((string) ($data['engagement_mode'] ?? QuestEngagementMode::OneTime->value))
            ?? QuestEngagementMode::OneTime;

        if ($mode !== QuestEngagementMode::RecurringInstallment || ! $this->categoryEligible($leaf)) {
            return [
                'engagement_mode' => QuestEngagementMode::OneTime,
                'installment_frequency' => null,
                'contract_duration_months' => null,
                'installment_count' => null,
                'installment_amount_minor' => null,
            ];
        }

        $months = (int) ($data['contract_duration_months'] ?? 0);
        if ($months < 1) {
            throw ValidationException::withMessages([
                'contract_duration_months' => [__('Choose how long the contract runs.')],
            ]);
        }

        $frequency = QuestInstallmentFrequency::tryFrom((string) ($data['installment_frequency'] ?? ''));
        if ($frequency === null) {
            throw ValidationException::withMessages([
                'installment_frequency' => [__('Choose how often the worker gets paid.')],
            ]);
        }
        $budgetMinor = (int) ($data['budget_amount_minor'] ?? 0);
        $count = $this->installmentCount($months, $frequency);
        $amountEach = $count > 0 ? (int) floor($budgetMinor / $count) : 0;

        $start = ! empty($data['scheduled_start_date'])
            ? Carbon::parse($data['scheduled_start_date'], $this->timezone())->startOfDay()
            : now($this->timezone())->startOfDay();

        $contractEnd = $start->copy()->addMonths($months)->endOfDay();

        return [
            'engagement_mode' => QuestEngagementMode::RecurringInstallment,
            'installment_frequency' => $frequency->value,
            'contract_duration_months' => $months,
            'installment_count' => $count,
            'installment_amount_minor' => $amountEach,
            'contract_starts_at' => $start,
            'contract_ends_at' => $contractEnd,
            'delivery_deadline' => $contractEnd->toDateString(),
            'estimated_delivery_date' => $contractEnd->toDateString(),
        ];
    }

    public function installmentCount(int $contractMonths, QuestInstallmentFrequency $frequency): int
    {
        if ($frequency === QuestInstallmentFrequency::Monthly) {
            return max(1, $contractMonths);
        }

        return max(1, (int) ceil(($contractMonths * 30) / 7));
    }

    public function generateSchedule(Quest $quest): void
    {
        if (! $this->isRecurring($quest) || (int) ($quest->installment_count ?? 0) < 1) {
            return;
        }

        if ($quest->paymentInstallments()->exists()) {
            return;
        }

        $frequency = QuestInstallmentFrequency::from((string) $quest->installment_frequency);
        $start = ($quest->contract_starts_at ?? $quest->escrow_funded_at ?? now())->copy()->timezone($this->timezone())->startOfDay();
        $totalMinor = (int) ($quest->budget_amount_minor ?? 0);
        $count = (int) $quest->installment_count;
        $baseAmount = (int) ($quest->installment_amount_minor ?? floor($totalMinor / max(1, $count)));
        $firstPeriodDays = (int) config('recurring_engagement.first_period_days', 7);

        DB::transaction(function () use ($quest, $frequency, $start, $totalMinor, $count, $baseAmount, $firstPeriodDays): void {
            $periodStart = $start->copy();
            $allocated = 0;
            $firstId = null;

            for ($i = 1; $i <= $count; $i++) {
                if ($i === 1) {
                    $periodEnd = $periodStart->copy()->addDays($firstPeriodDays)->endOfDay();
                } elseif ($frequency === QuestInstallmentFrequency::Monthly) {
                    $periodEnd = $periodStart->copy()->addMonth()->subDay()->endOfDay();
                } else {
                    $periodEnd = $periodStart->copy()->addDays(7)->endOfDay();
                }

                $amount = $i === $count ? max(0, $totalMinor - $allocated) : $baseAmount;
                $allocated += $amount;

                $status = $i === 1 ? QuestInstallmentStatus::Active : QuestInstallmentStatus::Pending;

                $row = QuestPaymentInstallment::query()->create([
                    'quest_id' => $quest->id,
                    'installment_number' => $i,
                    'period_start_at' => $periodStart,
                    'period_end_at' => $periodEnd,
                    'amount_minor' => $amount,
                    'status' => $status,
                ]);

                if ($i === 1) {
                    $firstId = $row->id;
                }

                $periodStart = $periodEnd->copy()->addDay()->startOfDay();
            }

            $quest->update([
                'current_installment_id' => $firstId,
                'contract_starts_at' => $start,
            ]);
        });
    }

    public function currentInstallment(Quest $quest): ?QuestPaymentInstallment
    {
        if (! $this->isRecurring($quest)) {
            return null;
        }

        if ($quest->current_installment_id) {
            return QuestPaymentInstallment::query()->find($quest->current_installment_id);
        }

        return $quest->paymentInstallments()
            ->whereIn('status', [
                QuestInstallmentStatus::Active,
                QuestInstallmentStatus::AwaitingReview,
                QuestInstallmentStatus::RevisionRequested,
                QuestInstallmentStatus::Approved,
            ])
            ->orderBy('installment_number')
            ->first();
    }

    public function syncQuestDeliveryStateFromInstallment(Quest $quest, QuestPaymentInstallment $installment): void
    {
        $quest->update([
            'delivered_at' => $installment->delivered_at,
            'delivery_review_deadline_at' => $installment->delivery_review_deadline_at,
            'delivery_revision_requested_at' => $installment->delivery_revision_requested_at,
            'delivery_revision_note' => $installment->delivery_revision_note,
            'delivery_acknowledged_at' => $installment->delivery_acknowledged_at,
            'latest_delivery_submission_id' => $installment->latest_delivery_submission_id,
        ]);
    }

    public function clearQuestDeliveryState(Quest $quest): void
    {
        $quest->update([
            'delivered_at' => null,
            'delivery_review_deadline_at' => null,
            'delivery_revision_requested_at' => null,
            'delivery_revision_requested_by' => null,
            'delivery_revision_note' => null,
            'delivery_acknowledged_at' => null,
            'delivery_acknowledged_by' => null,
        ]);
    }

    public function afterInstallmentReleased(Quest $quest, QuestPaymentInstallment $released): void
    {
        $released->update([
            'status' => QuestInstallmentStatus::Released,
            'released_at' => now(),
        ]);

        $next = $quest->paymentInstallments()
            ->where('installment_number', '>', $released->installment_number)
            ->orderBy('installment_number')
            ->first();

        $this->clearQuestDeliveryState($quest);

        if ($next === null) {
            $quest->update(['current_installment_id' => null]);

            return;
        }

        $next->update(['status' => QuestInstallmentStatus::Active]);
        $quest->update(['current_installment_id' => $next->id]);
    }

    public function markInstallmentDelivered(
        Quest $quest,
        QuestPaymentInstallment $installment,
        QuestDeliverySubmission $submission,
        Carbon $submittedAt,
        Carbon $reviewDeadline,
    ): void {
        $installment->update([
            'status' => QuestInstallmentStatus::AwaitingReview,
            'delivered_at' => $submittedAt,
            'delivery_review_deadline_at' => $reviewDeadline,
            'delivery_revision_requested_at' => null,
            'delivery_revision_note' => null,
            'latest_delivery_submission_id' => $submission->id,
        ]);

        $this->syncQuestDeliveryStateFromInstallment($quest->fresh(), $installment->fresh());
    }

    public function markInstallmentRevisionRequested(Quest $quest, QuestPaymentInstallment $installment, User $client, string $note): void
    {
        $installment->update([
            'status' => QuestInstallmentStatus::RevisionRequested,
            'delivery_revision_requested_at' => now(),
            'delivery_revision_note' => trim($note),
            'delivered_at' => null,
            'delivery_review_deadline_at' => null,
        ]);

        $quest->update([
            'delivery_revision_requested_at' => now(),
            'delivery_revision_requested_by' => $client->id,
            'delivery_revision_note' => trim($note),
            'delivered_at' => null,
            'delivery_review_deadline_at' => null,
        ]);
    }

    public function markInstallmentApproved(Quest $quest, QuestPaymentInstallment $installment, User $client): void
    {
        $now = now();

        $installment->update([
            'status' => QuestInstallmentStatus::Approved,
            'delivery_acknowledged_at' => $now,
        ]);

        $quest->update([
            'delivery_acknowledged_at' => $now,
            'delivery_acknowledged_by' => $client->id,
        ]);
    }

    public function markInstallmentAutoApproved(Quest $quest, QuestPaymentInstallment $installment): void
    {
        $now = now();

        $installment->update([
            'status' => QuestInstallmentStatus::Approved,
            'delivery_acknowledged_at' => $now,
        ]);

        $quest->update([
            'delivery_acknowledged_at' => $now,
            'delivery_acknowledged_by' => null,
        ]);
    }

    /**
     * Release the current installment (or full escrow for one-time jobs).
     *
     * @return array{quest_completed: bool, installment_number: ?int, amount_minor: int}
     */
    public function processApprovedRelease(
        Quest $quest,
        ?User $actor,
        ?string $reason,
        ?string $releaseTrigger = null,
    ): array {
        if (! $this->isRecurring($quest)) {
            app(\App\Services\Payments\EscrowPaymentService::class)->releaseEscrowToWallet(
                $quest->fresh(),
                $actor,
                $reason,
                releaseTrigger: $releaseTrigger,
            );

            return [
                'quest_completed' => true,
                'installment_number' => null,
                'amount_minor' => \App\Support\EscrowReleasePolicy::escrowAmountMinor($quest),
            ];
        }

        $installment = $this->currentInstallment($quest);
        if ($installment === null || $installment->status !== QuestInstallmentStatus::Approved) {
            throw ValidationException::withMessages([
                'escrow' => [__('This payment period is not ready for release yet.')],
            ]);
        }

        $amountMinor = (int) $installment->amount_minor;

        app(\App\Services\Payments\EscrowPaymentService::class)->releaseEscrowToWallet(
            $quest->fresh(),
            $actor,
            $reason ?? __('Installment :num released after delivery approval', ['num' => $installment->installment_number]),
            releaseTrigger: $releaseTrigger,
            amountMinor: $amountMinor,
            installmentId: (int) $installment->id,
        );

        $this->afterInstallmentReleased($quest->fresh(), $installment->fresh());

        return [
            'quest_completed' => $this->allInstallmentsReleased($quest->fresh()),
            'installment_number' => (int) $installment->installment_number,
            'amount_minor' => $amountMinor,
        ];
    }

    public function completeQuestAfterFinalInstallment(Quest $quest, ?User $actor, string $closureType): void
    {
        $quest->update([
            'status' => QuestStatus::Completed,
            'completed_at' => now(),
            'funds_released_at' => now(),
            'completed_on_time' => true,
            'closure_type' => $closureType,
        ]);

        $contract = \App\Models\QuestContract::query()->where('quest_id', $quest->id)->first();
        if ($contract !== null) {
            app(\App\Services\Contracts\ContractLifecycleService::class)->markCompleted($contract, $actor);
        }
    }

    public function allInstallmentsReleased(Quest $quest): bool
    {
        if (! $this->isRecurring($quest)) {
            return false;
        }

        return ! $quest->paymentInstallments()
            ->where('status', '!=', QuestInstallmentStatus::Released->value)
            ->exists();
    }

    public function releaseAmountMinor(Quest $quest): int
    {
        if (! $this->isRecurring($quest)) {
            return \App\Support\EscrowReleasePolicy::escrowAmountMinor($quest);
        }

        $installment = $this->currentInstallment($quest);
        if ($installment === null || $installment->status !== QuestInstallmentStatus::Approved) {
            return 0;
        }

        return (int) $installment->amount_minor;
    }

    /**
     * @return array<string, mixed>
     */
    public function uiPayload(Quest $quest, ?\App\Models\User $viewer): array
    {
        if (! $this->isRecurring($quest)) {
            return ['is_recurring' => false];
        }

        $quest->loadMissing(['paymentInstallments', 'currentInstallment']);
        $current = $this->currentInstallment($quest);
        $frequency = QuestInstallmentFrequency::tryFrom((string) $quest->installment_frequency);

        return [
            'is_recurring' => true,
            'frequency_label' => $frequency?->label(),
            'contract_starts_label' => $quest->contract_starts_at?->timezone($this->timezone())->format('j M Y'),
            'contract_ends_label' => $quest->contract_ends_at?->timezone($this->timezone())->format('j M Y'),
            'installment_count' => (int) $quest->installment_count,
            'installment_amount_label' => \App\Support\NgnMoney::format((int) ($quest->installment_amount_minor ?? 0)),
            'current_installment' => $current ? $this->mapInstallment($current) : null,
            'installments' => $quest->paymentInstallments->map(fn (QuestPaymentInstallment $i) => $this->mapInstallment($i))->all(),
            'plain_english' => __('Full contract amount is in escrow. You approve each :freq period, then that slice is paid out. The job ends on :end unless you extend.', [
                'freq' => strtolower($frequency?->label() ?? 'period'),
                'end' => $quest->contract_ends_at?->timezone($this->timezone())->format('j M Y') ?? '—',
            ]),
            'contract_renewal' => app(QuestContractRenewalService::class)->uiPayload($quest, $viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalTermsPayload(Quest $quest): array
    {
        if (! $this->isRecurring($quest)) {
            return ['required' => false];
        }

        $frequency = QuestInstallmentFrequency::tryFrom((string) $quest->installment_frequency);

        return [
            'required' => true,
            'frequency' => $quest->installment_frequency,
            'frequency_label' => $frequency?->label(),
            'contract_months' => (int) $quest->contract_duration_months,
            'installment_count' => (int) $quest->installment_count,
            'installment_amount_label' => \App\Support\NgnMoney::format((int) ($quest->installment_amount_minor ?? 0)),
            'total_budget_label' => \App\Support\NgnMoney::format((int) ($quest->budget_amount_minor ?? 0)),
            'summary' => __('This is an ongoing job paid in :freq installments (:amount each) for :months months. Full escrow is funded upfront; you are paid after each period when the client approves your delivery.', [
                'freq' => strtolower($frequency?->label() ?? 'period'),
                'amount' => \App\Support\NgnMoney::format((int) ($quest->installment_amount_minor ?? 0)),
                'months' => (int) $quest->contract_duration_months,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInstallment(QuestPaymentInstallment $installment): array
    {
        return [
            'id' => $installment->id,
            'number' => $installment->installment_number,
            'status' => $installment->status->value,
            'status_label' => $installment->status->label(),
            'amount_label' => \App\Support\NgnMoney::format((int) $installment->amount_minor),
            'amount_minor' => (int) $installment->amount_minor,
            'period_start_label' => $installment->period_start_at->timezone($this->timezone())->format('j M Y'),
            'period_end_label' => $installment->period_end_at->timezone($this->timezone())->format('j M Y'),
            'released_at_label' => $installment->released_at?->timezone($this->timezone())->format('j M Y, g:i A'),
        ];
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Africa/Lagos');
    }
}
