<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeReason;
use App\Models\QuestDispute;

class DisputeWorkflowChecklistService
{
    /**
     * @return list<array{key: string, label: string, optional: bool}>
     */
    public function stepsFor(QuestDispute $dispute): array
    {
        $reason = QuestDisputeReason::tryFrom((string) $dispute->reason);
        $category = $reason?->category()->value ?? 'general';
        $base = $this->baseSteps();

        return match (true) {
            in_array($category, ['payment_contract', 'payment_terms'], true) => array_merge([
                ['key' => 'review_escrow', 'label' => __('Verify escrow status and payment timeline'), 'optional' => false],
            ], $base),
            in_array($category, ['scope_requirements', 'scope_communication'], true) => array_merge([
                ['key' => 'review_scope_spec', 'label' => __('Compare original scope vs delivered work'), 'optional' => false],
            ], $base),
            default => $base,
        };
    }

    /**
     * @return list<array{key: string, label: string, optional: bool}>
     */
    private function baseSteps(): array
    {
        return [
            ['key' => 'review_claim', 'label' => __('Review original dispute claim'), 'optional' => false],
            ['key' => 'review_evidence', 'label' => __('Check all evidence files'), 'optional' => false],
            ['key' => 'review_conversation', 'label' => __('Review full conversation history'), 'optional' => false],
            ['key' => 'request_evidence_if_needed', 'label' => __('Request additional evidence (if needed)'), 'optional' => true],
            ['key' => 'rate_quality', 'label' => __('Rate investigation quality (1–5 stars)'), 'optional' => false],
            ['key' => 'make_finding', 'label' => __('Make finding (partial/full/no violation)'), 'optional' => false],
            ['key' => 'write_assessment', 'label' => __('Write assessment + recommendation'), 'optional' => false],
            ['key' => 'submit_for_super_admin', 'label' => __('Submit for Super Admin review'), 'optional' => false],
        ];
    }

    /**
     * @return array{steps: list<array<string, mixed>>, completed: list<string>, progress_percent: int, required_total: int, required_completed: int}
     */
    public function payload(QuestDispute $dispute): array
    {
        $steps = $this->stepsFor($dispute);
        $state = $dispute->workflow_state ?? [];
        $completed = array_values(array_unique(array_filter((array) ($state['checklist_completed'] ?? []))));

        $required = collect($steps)->where('optional', false);
        $requiredKeys = $required->pluck('key')->all();
        $requiredCompleted = count(array_intersect($completed, $requiredKeys));
        $requiredTotal = count($requiredKeys);
        $progress = $requiredTotal > 0 ? (int) round(($requiredCompleted / $requiredTotal) * 100) : 0;

        $mapped = collect($steps)->map(fn (array $step): array => [
            ...$step,
            'completed' => in_array($step['key'], $completed, true),
        ])->values()->all();

        return [
            'steps' => $mapped,
            'completed' => $completed,
            'progress_percent' => min(100, $progress),
            'required_total' => $requiredTotal,
            'required_completed' => $requiredCompleted,
        ];
    }

    /**
     * @param  list<string>  $completedKeys
     */
    public function saveCompleted(QuestDispute $dispute, array $completedKeys): QuestDispute
    {
        $state = $dispute->workflow_state ?? [];
        $state['checklist_completed'] = array_values(array_unique($completedKeys));

        $dispute->forceFill(['workflow_state' => $state])->save();

        return $dispute->fresh();
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public function patchState(QuestDispute $dispute, array $patch): QuestDispute
    {
        $state = array_merge($dispute->workflow_state ?? [], $patch);
        $dispute->forceFill(['workflow_state' => $state])->save();

        return $dispute->fresh();
    }

    public function autoComplete(QuestDispute $dispute, string $key): void
    {
        $completed = (array) data_get($dispute->workflow_state, 'checklist_completed', []);
        if (! in_array($key, $completed, true)) {
            $completed[] = $key;
            $this->saveCompleted($dispute, $completed);
        }
    }
}
