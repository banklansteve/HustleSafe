<?php

namespace App\Services\Proposals;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\Quest\QuestRecurringEngagementService;
use App\Support\EscrowAutoReleasePolicy;
use App\Support\ProposalMoneyCalculator;
use Carbon\Carbon;

class ProposalClarificationPromptService
{
    public const MAX_QUESTIONS = 8;

    /**
     * @return list<array{key: string, category: string, label: string, question: string, hint: string|null}>
     */
    public function suggestedPrompts(Quest $quest, QuestOffer $offer): array
    {
        $quest->loadMissing(['questCategory.parent']);
        $offer->loadMissing('freelancer');

        $prompts = [];
        $quoteMinor = (int) ($offer->quoted_amount_minor ?? 0);
        $budgetMinor = (int) ($quest->budget_amount_minor ?? 0);

        $prompts[] = [
            'key' => 'scope_alignment',
            'category' => 'scope',
            'label' => 'Scope alignment',
            'question' => __('Before I award, can you confirm your proposal covers everything in my quest brief — including deliverables, revisions, and anything you called out in your pitch?'),
            'hint' => __('Locks in what “done” means before escrow.'),
        ];

        if ($quoteMinor > 0 && $budgetMinor > 0 && abs($quoteMinor - $budgetMinor) / max(1, $budgetMinor) >= 0.15) {
            $prompts[] = [
                'key' => 'budget_alignment',
                'category' => 'budget',
                'label' => 'Budget vs brief',
                'question' => __('My quest budget is around :budget but your quote is :quote — can you walk me through what is included at that price?', [
                    'budget' => $this->money($budgetMinor),
                    'quote' => $this->money($quoteMinor),
                ]),
                'hint' => null,
            ];
        }

        $finish = $offer->planned_finish_date ?? $offer->proposed_completion_date;
        if ($finish) {
            $prompts[] = [
                'key' => 'timeline_finish',
                'category' => 'timeline',
                'label' => 'Finish date',
                'question' => __('You proposed finishing around :date — is that still realistic if I award within the next few days?', [
                    'date' => Carbon::parse($finish)->format('j M Y'),
                ]),
                'hint' => null,
            ];
        } elseif ($offer->estimated_duration_days) {
            $prompts[] = [
                'key' => 'timeline_duration',
                'category' => 'timeline',
                'label' => 'Duration',
                'question' => __('You estimated :days days — what is the first milestone I should expect, and when?', [
                    'days' => (int) $offer->estimated_duration_days,
                ]),
                'hint' => null,
            ];
        }

        $materials = $offer->materials ?? [];
        if (is_array($materials) && count($materials) > 0) {
            $prompts[] = [
                'key' => 'materials_included',
                'category' => 'materials',
                'label' => 'Materials & extras',
                'question' => __('Does your quote of :quote include all materials and extras listed in your proposal, or should I budget separately?', [
                    'quote' => $this->money($quoteMinor),
                ]),
                'hint' => null,
            ];
        }

        if ($offer->corrections_included) {
            $rounds = $offer->corrections_rounds ? (int) $offer->corrections_rounds : 1;
            $prompts[] = [
                'key' => 'revisions_scope',
                'category' => 'revisions',
                'label' => 'Revisions',
                'question' => __('You included :rounds revision round(s) — what counts as a revision vs new scope?', [
                    'rounds' => $rounds,
                ]),
                'hint' => null,
            ];
        }

        if ($quest->site_visits_allowed) {
            $prompts[] = [
                'key' => 'site_visit',
                'category' => 'site',
                'label' => 'Site access',
                'question' => __('Site visits may be needed for this quest — have you accounted for travel, access constraints, and timing in your quote?'),
                'hint' => null,
            ];
        }

        if ($offer->progress_report_frequency) {
            $prompts[] = [
                'key' => 'reporting_cadence',
                'category' => 'communication',
                'label' => 'Progress updates',
                'question' => __('You offered :freq progress updates — what format will those take on-platform before delivery?', [
                    'freq' => str_replace('_', ' ', (string) $offer->progress_report_frequency),
                ]),
                'hint' => null,
            ];
        }

        if ($offer->warranty_terms) {
            $prompts[] = [
                'key' => 'warranty',
                'category' => 'quality',
                'label' => 'Warranty / support',
                'question' => __('Can you confirm your warranty/support terms after delivery, as written in your proposal?'),
                'hint' => null,
            ];
        }

        $prompts[] = [
            'key' => 'blockers',
            'category' => 'risk',
            'label' => 'Blockers',
            'question' => __('Is there anything you still need from me (assets, access, approvals) before you could start once escrow is funded?'),
            'hint' => __('Surfaces surprises before award.'),
        ];

        return array_slice($prompts, 0, 10);
    }

    /**
     * @return array<string, mixed>
     */
    public function awardTermsSnapshot(Quest $quest, QuestOffer $offer): array
    {
        $quest->loadMissing('client');
        $start = $offer->planned_start_date;
        $finish = $offer->planned_finish_date ?? $offer->proposed_completion_date;
        if (! $finish && $offer->estimated_duration_days) {
            $finish = now()->addDays((int) $offer->estimated_duration_days)->toDateString();
        }

        $snapshot = is_array($offer->pricing_snapshot) ? $offer->pricing_snapshot : [];
        $payout = $this->awardPayoutBreakdown($offer);
        $finishDate = $finish ? Carbon::parse($finish)->toDateString() : null;

        $terms = [
            'quest_title' => $quest->title,
            'client_name' => $quest->client?->name,
            'offer_id' => $offer->id,
            'price_minor' => $payout['quote_minor'],
            'price_label' => $payout['quote_label'],
            'start_date' => $start ? Carbon::parse($start)->toDateString() : null,
            'start_label' => $start ? Carbon::parse($start)->format('j M Y') : null,
            'deadline_label' => $finish ? Carbon::parse($finish)->format('j M Y') : null,
            'deadline_date' => $finishDate,
            'duration_days' => $offer->estimated_duration_days ? (int) $offer->estimated_duration_days : null,
            'duration_label' => $offer->estimated_duration_days
                ? __(':days days', ['days' => (int) $offer->estimated_duration_days])
                : null,
            'progress_report_frequency' => $offer->progress_report_frequency,
            'progress_report_label' => $this->progressReportLabel($offer),
            'revisions_included' => (int) ($offer->corrections_included ? ($offer->corrections_rounds ?: 1) : 0),
            'revision_definition' => $this->deriveRevisionDefinition($offer),
            'warranty_terms' => $offer->warranty_terms
                ? str(trim(strip_tags((string) $offer->warranty_terms)))->limit(500)->toString()
                : null,
            'payout' => $payout,
            'release_timing' => $this->releaseTimingSnapshot($finishDate),
        ];

        $recurring = app(QuestRecurringEngagementService::class);
        if ($recurring->isRecurring($quest)) {
            $terms['installment_schedule'] = $this->freelancerInstallmentSchedule($quest, $offer);
        }

        return $terms;
    }

    /**
     * What the freelancer receives in their wallet when escrow is released.
     * Platform fee and VAT are client-side charges — not deducted from the freelancer quote.
     *
     * @return array<string, mixed>
     */
    public function awardPayoutBreakdown(QuestOffer $offer): array
    {
        $snapshot = is_array($offer->pricing_snapshot) ? $offer->pricing_snapshot : [];
        $components = ProposalMoneyCalculator::freelancerQuoteComponents($snapshot);
        $quoteMinor = $components['quote_minor'] > 0
            ? $components['quote_minor']
            : ProposalMoneyCalculator::freelancerWalletPayoutMinor($snapshot);

        if ($quoteMinor <= 0) {
            $quoteMinor = max(0, (int) ($offer->quoted_amount_minor ?? 0));
        }

        return [
            'professional_fee_minor' => $components['professional_fee_minor'],
            'professional_fee_label' => $this->money($components['professional_fee_minor']),
            'materials_minor' => $components['materials_minor'],
            'materials_label' => $this->money($components['materials_minor']),
            'travel_minor' => $components['travel_minor'],
            'travel_label' => $this->money($components['travel_minor']),
            'discount_minor' => $components['discount_minor'],
            'discount_label' => $this->money($components['discount_minor']),
            'quote_minor' => $quoteMinor,
            'quote_label' => $this->money($quoteMinor),
            'net_to_wallet_minor' => $quoteMinor,
            'net_to_wallet_label' => $this->money($quoteMinor),
            'summary' => $components['discount_minor'] > 0
                ? __('When the client approves your finished work, :amount is released to your wallet (your quote including materials and travel, minus the agreed discount).', [
                    'amount' => $this->money($quoteMinor),
                ])
                : __('When the client approves your finished work, :amount is released to your wallet.', [
                    'amount' => $this->money($quoteMinor),
                ]),
        ];
    }

    /**
     * Recompute payout lines from the live pricing snapshot (fixes stale award_terms_snapshot rows).
     *
     * @param  array<string, mixed>  $terms
     * @return array<string, mixed>
     */
    public function refreshAwardTermsForDisplay(array $terms, QuestOffer $offer): array
    {
        $payout = $this->awardPayoutBreakdown($offer);
        $terms['payout'] = $payout;
        $terms['price_minor'] = $payout['quote_minor'];
        $terms['price_label'] = $payout['quote_label'];

        return $terms;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function releaseTimingSnapshot(?string $finishDate): ?array
    {
        if ($finishDate === null || trim($finishDate) === '') {
            return null;
        }

        $tz = 'Africa/Lagos';
        $due = Carbon::parse($finishDate, $tz)->endOfDay();
        $releaseAt = EscrowAutoReleasePolicy::releaseAt($due);
        $hours = EscrowAutoReleasePolicy::releaseHours();

        return [
            'headline' => __('After client approves delivery OR'),
            'auto_release_label' => $releaseAt->timezone($tz)->format('j M Y, g:i A'),
            'auto_release_hours' => $hours,
            'footnote' => __('(:hours hours after delivery)', ['hours' => $hours]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function freelancerInstallmentSchedule(Quest $quest, QuestOffer $offer): array
    {
        $recurring = app(QuestRecurringEngagementService::class);
        $base = $recurring->proposalTermsPayload($quest);
        $snapshot = is_array($offer->pricing_snapshot) ? $offer->pricing_snapshot : [];
        $walletTotal = ProposalMoneyCalculator::freelancerWalletPayoutMinor($snapshot);
        if ($walletTotal <= 0) {
            $walletTotal = max(0, (int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0));
        }

        $count = max(1, (int) ($quest->installment_count ?? $base['installment_count'] ?? 1));
        $perPayment = ProposalMoneyCalculator::freelancerInstallmentPayoutMinor($snapshot, $count);
        if ($perPayment <= 0 && $count > 0) {
            $perPayment = (int) floor($walletTotal / $count);
        }

        return array_merge($base, [
            'wallet_total_minor' => $walletTotal,
            'wallet_total_label' => $this->money($walletTotal),
            'per_payment_minor' => $perPayment,
            'per_payment_label' => $this->money($perPayment),
            'summary' => __('You receive :per_payment after each approved period — :count payments totalling :total to your wallet.', [
                'per_payment' => $this->money($perPayment),
                'count' => $count,
                'total' => $this->money($walletTotal),
            ]),
        ]);
    }

    private function progressReportLabel(QuestOffer $offer): ?string
    {
        $key = (string) ($offer->progress_report_frequency ?? '');
        if ($key === '') {
            return null;
        }

        if ($key === 'custom') {
            $note = trim((string) ($offer->progress_report_frequency_note ?? ''));

            return $note !== '' ? $note : __('Custom schedule');
        }

        return match ($key) {
            'daily' => __('Daily'),
            'twice_weekly' => __('Twice weekly'),
            'weekly' => __('Weekly'),
            'biweekly' => __('Bi-weekly'),
            'milestone_based' => __('At milestones'),
            'on_request' => __('On request'),
            default => $key,
        };
    }

    /**
     * @return list<array{title: string, description: string|null}>
     */
    public function deriveDeliverables(Quest $quest, QuestOffer $offer): array
    {
        $title = trim((string) $quest->title);
        if ($title === '') {
            $title = __('Agreed work');
        }

        $scope = trim(strip_tags((string) ($offer->scope_detail ?: $offer->pitch ?: '')));

        return [
            [
                'title' => $title,
                'description' => $scope !== '' ? str($scope)->limit(2000)->toString() : null,
            ],
        ];
    }

    public function deriveRevisionDefinition(QuestOffer $offer): string
    {
        if (! $offer->corrections_included) {
            return __('Small touch-ups after delivery are not included unless you both agree separately.');
        }

        $rounds = max(1, (int) ($offer->corrections_rounds ?: 1));

        return $rounds === 1
            ? __('One round of small fixes is included — correcting mistakes in the finished work, not adding new work.')
            : __(':rounds rounds of small fixes are included — correcting mistakes in the finished work, not adding new work.', ['rounds' => $rounds]);
    }

    private function money(int $minor): string
    {
        return '₦'.number_format($minor / 100, 0);
    }
}
