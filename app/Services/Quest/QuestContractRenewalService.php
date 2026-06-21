<?php

namespace App\Services\Quest;

use App\Enums\QuestEngagementMode;
use App\Enums\QuestInstallmentFrequency;
use App\Enums\QuestInstallmentStatus;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestPaymentInstallment;
use App\Models\User;
use App\Services\QuestSlugService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QuestContractRenewalService
{
    public function __construct(
        private readonly QuestRecurringEngagementService $recurring,
        private readonly QuestSlugService $slugs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function uiPayload(Quest $quest, ?User $viewer): array
    {
        if (! $this->recurring->isRecurring($quest)) {
            return ['show_panel' => false];
        }

        $isClient = $viewer !== null && (int) $viewer->id === (int) $quest->client_id;
        $windowDays = $this->renewalWindowDays();
        $endsAt = $quest->contract_ends_at?->copy()->timezone($this->timezone());
        $windowOpensAt = $endsAt?->copy()->subDays($windowDays);
        $now = now($this->timezone());
        $allReleased = $this->recurring->allInstallmentsReleased($quest);
        $inWindow = $endsAt !== null
            && $windowOpensAt !== null
            && $now->greaterThanOrEqualTo($windowOpensAt)
            && ($now->lessThanOrEqualTo($endsAt) || $allReleased);
        $funded = in_array($quest->escrow_status, ['funded', 'partially_released', 'released'], true);
        $engaged = in_array($quest->status?->value ?? (string) $quest->status, [
            QuestStatus::Assigned->value,
            QuestStatus::InProgress->value,
            QuestStatus::Completed->value,
        ], true);

        $durationOptions = collect(config('recurring_engagement.contract_duration_options', []))
            ->map(fn ($label, $months) => [
                'value' => (int) $months,
                'label' => __($label),
            ])
            ->values()
            ->all();

        return [
            'show_panel' => $isClient && $funded && $engaged && $inWindow,
            'is_client' => $isClient,
            'contract_ends_label' => $endsAt?->format('j M Y'),
            'window_opens_label' => $windowOpensAt?->format('j M Y'),
            'window_days' => $windowDays,
            'all_installments_released' => $allReleased,
            'duration_options' => $durationOptions,
            'extend_url' => route('quests.contract-renewal.extend', $quest),
            'continue_url' => route('quests.contract-renewal.continue', $quest),
            'republish_url' => route('quests.contract-renewal.republish', $quest),
            'plain_english' => __('When this contract ends on :date, you can extend it with the same worker, start a fresh cycle with them, or republish the quest for new proposals.', [
                'date' => $endsAt?->format('j M Y') ?? '—',
            ]),
        ];
    }

    public function extend(Quest $quest, User $client, int $additionalMonths): Quest
    {
        $this->assertClientCanRenew($quest, $client);
        $this->assertWithinRenewalWindow($quest);

        if ($this->recurring->allInstallmentsReleased($quest)) {
            throw ValidationException::withMessages([
                'additional_months' => [__('All payments for this contract are done. Choose “Continue with worker” to start a new cycle instead.')],
            ]);
        }

        $allowed = array_keys(config('recurring_engagement.contract_duration_options', []));
        if (! in_array($additionalMonths, $allowed, true)) {
            throw ValidationException::withMessages([
                'additional_months' => [__('Choose a valid contract extension length.')],
            ]);
        }

        return DB::transaction(function () use ($quest, $additionalMonths): Quest {
            $frequency = QuestInstallmentFrequency::from((string) $quest->installment_frequency);
            $newCount = $this->recurring->installmentCount($additionalMonths, $frequency);
            $baseAmount = (int) ($quest->installment_amount_minor ?? 0);
            $last = $quest->paymentInstallments()->orderByDesc('installment_number')->first();

            if ($last === null) {
                throw ValidationException::withMessages([
                    'quest' => [__('Installment schedule is missing for this contract.')],
                ]);
            }

            $periodStart = $last->period_end_at->copy()->addDay()->startOfDay();
            $totalAdditional = $baseAmount * $newCount;
            $allocated = 0;
            $startNumber = (int) $last->installment_number + 1;

            for ($i = 0; $i < $newCount; $i++) {
                $number = $startNumber + $i;
                if ($frequency === QuestInstallmentFrequency::Monthly) {
                    $periodEnd = $periodStart->copy()->addMonth()->subDay()->endOfDay();
                } else {
                    $periodEnd = $periodStart->copy()->addDays(7)->endOfDay();
                }

                $amount = ($i === $newCount - 1)
                    ? max(0, $totalAdditional - $allocated)
                    : $baseAmount;
                $allocated += $amount;

                QuestPaymentInstallment::query()->create([
                    'quest_id' => $quest->id,
                    'installment_number' => $number,
                    'period_start_at' => $periodStart,
                    'period_end_at' => $periodEnd,
                    'amount_minor' => $amount,
                    'status' => QuestInstallmentStatus::Pending,
                ]);

                $periodStart = $periodEnd->copy()->addDay()->startOfDay();
            }

            $additionalBudget = $totalAdditional;
            $newEnd = ($quest->contract_ends_at ?? now())->copy()->addMonths($additionalMonths)->endOfDay();

            $quest->update([
                'contract_duration_months' => (int) ($quest->contract_duration_months ?? 0) + $additionalMonths,
                'installment_count' => (int) ($quest->installment_count ?? 0) + $newCount,
                'contract_ends_at' => $newEnd,
                'delivery_deadline' => $newEnd->toDateString(),
                'estimated_delivery_date' => $newEnd->toDateString(),
                'budget_amount_minor' => (int) ($quest->budget_amount_minor ?? 0) + $additionalBudget,
                'escrow_status' => 'awaiting_funding',
                'status' => QuestStatus::Assigned,
            ]);

            $this->recurring->clearQuestDeliveryState($quest->fresh());

            return $quest->fresh();
        });
    }

    public function continueWithFreelancer(Quest $quest, User $client, int $months): Quest
    {
        $this->assertClientCanRenew($quest, $client);
        $this->assertWithinRenewalWindow($quest);

        $allowed = array_keys(config('recurring_engagement.contract_duration_options', []));
        if (! in_array($months, $allowed, true)) {
            throw ValidationException::withMessages([
                'contract_duration_months' => [__('Choose a valid contract length.')],
            ]);
        }

        if ($quest->freelancer_id === null) {
            throw ValidationException::withMessages([
                'quest' => [__('This quest has no assigned worker to continue with.')],
            ]);
        }

        return DB::transaction(function () use ($quest, $months): Quest {
            $frequency = QuestInstallmentFrequency::from((string) $quest->installment_frequency);
            $count = $this->recurring->installmentCount($months, $frequency);
            $budgetMinor = (int) ($quest->budget_amount_minor ?? 0);
            $amountEach = $count > 0 ? (int) floor($budgetMinor / $count) : 0;
            $start = now($this->timezone())->startOfDay();
            $end = $start->copy()->addMonths($months)->endOfDay();

            $quest->paymentInstallments()->delete();

            $quest->update([
                'contract_duration_months' => $months,
                'installment_count' => $count,
                'installment_amount_minor' => $amountEach,
                'contract_starts_at' => $start,
                'contract_ends_at' => $end,
                'delivery_deadline' => $end->toDateString(),
                'estimated_delivery_date' => $end->toDateString(),
                'current_installment_id' => null,
                'escrow_status' => 'awaiting_funding',
                'status' => QuestStatus::Assigned,
                'completed_at' => null,
                'funds_released_at' => null,
                'closure_type' => null,
            ]);

            $this->recurring->clearQuestDeliveryState($quest->fresh());
            $this->recurring->generateSchedule($quest->fresh());

            return $quest->fresh();
        });
    }

    public function republishForProposals(Quest $quest, User $client): Quest
    {
        $this->assertClientCanRenew($quest, $client);
        $this->assertWithinRenewalWindow($quest);

        return DB::transaction(function () use ($quest, $client): Quest {
            $quest->update([
                'status' => QuestStatus::Completed,
                'completed_at' => now(),
                'closure_type' => 'renewed_republished',
            ]);

            $slug = $this->slugs->uniqueSlugFromTitle($quest->title);
            $days = \App\Support\PlatformSettings::proposalDeadlineBounds()['default'];

            $fresh = Quest::query()->create([
                'client_id' => $client->id,
                'slug' => $slug,
                'title' => $quest->title,
                'description' => $quest->description,
                'quest_category_id' => $quest->quest_category_id,
                'state_id' => $quest->state_id,
                'local_government_id' => $quest->local_government_id,
                'city' => $quest->city,
                'status' => QuestStatus::Open,
                'visibility' => $quest->visibility,
                'engagement_mode' => $quest->engagement_mode instanceof QuestEngagementMode
                    ? $quest->engagement_mode->value
                    : (string) ($quest->engagement_mode ?? QuestEngagementMode::RecurringInstallment->value),
                'installment_frequency' => $quest->installment_frequency,
                'contract_duration_months' => $quest->contract_duration_months,
                'installment_count' => $quest->installment_count,
                'installment_amount_minor' => $quest->installment_amount_minor,
                'budget_amount_minor' => $quest->budget_amount_minor,
                'freelancer_location_pref' => $quest->freelancer_location_pref,
                'availability_need' => $quest->availability_need,
                'project_type' => $quest->project_type,
                'estimated_hours' => $quest->estimated_hours,
                'team_size' => $quest->team_size,
                'auto_listing_expiry_days' => $days,
                'listing_expires_at' => now()->addDays($days),
                'max_offers' => $quest->max_offers,
                'start_timing' => $quest->start_timing,
                'scheduled_start_date' => $quest->scheduled_start_date,
                'reposted_from_quest_id' => $quest->id,
                'terms_accepted_at' => now(),
            ]);

            return $fresh;
        });
    }

    public function createExplainerPayload(): array
    {
        return [
            'title' => __('What happens when the contract ends?'),
            'body' => __('Before the contract finishes, you can extend it with the same worker, start a new paid cycle with them, or republish the quest so other freelancers can propose. Payments stay on the same weekly or monthly schedule until you choose.'),
            'options' => [
                ['key' => 'extend', 'label' => __('Extend'), 'detail' => __('Add more months to the current contract and fund any extra escrow needed.')],
                ['key' => 'continue', 'label' => __('Continue with worker'), 'detail' => __('Start a fresh cycle with the same freelancer — new escrow funding, same payment rhythm.')],
                ['key' => 'republish', 'label' => __('Republish for proposals'), 'detail' => __('Close this cycle and open a new quest listing for fresh proposals.')],
                ['key' => 'decide_later', 'label' => __('Decide later'), 'detail' => __('Postpone this decision until the end of the first contract — you can choose then.')],
            ],
        ];
    }

    private function renewalWindowDays(): int
    {
        return max(1, (int) config('recurring_engagement.renewal_window_days', 14));
    }

    private function assertClientCanRenew(Quest $quest, User $client): void
    {
        if ((int) $quest->client_id !== (int) $client->id) {
            abort(403);
        }

        if (! $this->recurring->isRecurring($quest)) {
            throw ValidationException::withMessages([
                'quest' => [__('Contract renewal is only available for ongoing installment jobs.')],
            ]);
        }

        if (! in_array($quest->escrow_status, ['funded', 'partially_released', 'released'], true)) {
            throw ValidationException::withMessages([
                'quest' => [__('Fund escrow before managing contract renewal.')],
            ]);
        }
    }

    private function assertWithinRenewalWindow(Quest $quest): void
    {
        $endsAt = $quest->contract_ends_at?->copy()->timezone($this->timezone());
        if ($endsAt === null) {
            throw ValidationException::withMessages([
                'quest' => [__('This contract has no end date yet.')],
            ]);
        }

        $windowOpensAt = $endsAt->copy()->subDays($this->renewalWindowDays());
        $now = now($this->timezone());
        $allReleased = $this->recurring->allInstallmentsReleased($quest);

        if ($now->lt($windowOpensAt)) {
            throw ValidationException::withMessages([
                'quest' => [__('Renewal options open :days days before the contract ends.', ['days' => $this->renewalWindowDays()])],
            ]);
        }

        if ($now->gt($endsAt) && ! $allReleased) {
            throw ValidationException::withMessages([
                'quest' => [__('Finish the remaining payment periods before renewing this contract.')],
            ]);
        }
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Africa/Lagos');
    }
}
