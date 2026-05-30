<?php

namespace App\Services\Proposals;

use App\Models\Quest;
use App\Models\QuestOffer;
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
     * @return array{scope_summary: string, price_minor: int, price_label: string, deadline_label: string|null, deadline_date: string|null}
     */
    public function awardTermsSnapshot(Quest $quest, QuestOffer $offer): array
    {
        $finish = $offer->planned_finish_date ?? $offer->proposed_completion_date;
        if (! $finish && $offer->estimated_duration_days) {
            $finish = now()->addDays((int) $offer->estimated_duration_days)->toDateString();
        }

        $scope = trim(strip_tags((string) ($offer->scope_detail ?: $offer->pitch ?: '')));
        if ($scope === '') {
            $scope = __('Work as described in the accepted proposal and quest brief.');
        }

        return [
            'scope_summary' => str($scope)->limit(2000)->toString(),
            'price_minor' => (int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0),
            'price_label' => $this->money((int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0)),
            'deadline_label' => $finish ? Carbon::parse($finish)->format('j M Y') : null,
            'deadline_date' => $finish ? Carbon::parse($finish)->toDateString() : null,
            'quest_title' => $quest->title,
            'offer_id' => $offer->id,
        ];
    }

    private function money(int $minor): string
    {
        return '₦'.number_format($minor / 100, 0);
    }
}
