<?php

namespace App\Services\Quest;

use App\Enums\ConversationFlagCategory;
use App\Services\ConversationMonitoring\ConversationMonitoringScanner;
use App\Support\PlatformSettings;

class QuestQualityGateService
{
    public function __construct(
        private readonly ConversationMonitoringScanner $scanner,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{passed: bool, issues: list<array{field: string, code: string, message: string}>}
     */
    public function evaluate(array $payload): array
    {
        $issues = [];

        $title = trim((string) ($payload['title'] ?? ''));
        $descriptionHtml = (string) ($payload['description'] ?? '');
        $plainDescription = trim(html_entity_decode(strip_tags($descriptionHtml)));
        $wordCount = $this->wordCount($plainDescription);
        $minWords = max(30, (int) ceil($this->minDescriptionChars() / 4));

        if ($wordCount < $minWords) {
            $issues[] = [
                'field' => 'description',
                'code' => 'description_too_short',
                'message' => __('Your description needs at least :count words so freelancers understand the scope. You currently have :current.', [
                    'count' => $minWords,
                    'current' => $wordCount,
                ]),
            ];
        }

        if (! $this->hasDeliverablesSignal($descriptionHtml, $plainDescription)) {
            $issues[] = [
                'field' => 'description',
                'code' => 'deliverables_missing',
                'message' => __('Spell out what you expect delivered — use a bullet list or a clear “You will receive…” section in the description.'),
            ];
        }

        if (empty($payload['quest_category_id'])) {
            $issues[] = [
                'field' => 'quest_category_id',
                'code' => 'category_missing',
                'message' => __('Choose a subcategory so your quest reaches the right freelancers.'),
            ];
        }

        if (empty($payload['budget_amount_minor']) || (int) $payload['budget_amount_minor'] < 10000) {
            $issues[] = [
                'field' => 'budget_amount_minor',
                'code' => 'budget_missing',
                'message' => __('Set a realistic budget before publishing.'),
            ];
        }

        $hasDeadline = ! empty($payload['estimated_delivery_date'])
            || (! empty($payload['estimated_completion_days']) && (int) $payload['estimated_completion_days'] > 0);

        if (! $hasDeadline) {
            $issues[] = [
                'field' => 'estimated_completion_days',
                'code' => 'deadline_missing',
                'message' => __('Add a timeline — estimated duration or a target delivery date.'),
            ];
        }

        foreach ($this->offPlatformIssues($title.' '.$plainDescription) as $issue) {
            $issues[] = $issue;
        }

        return [
            'passed' => $issues === [],
            'issues' => $issues,
        ];
    }

    private function minDescriptionChars(): int
    {
        return max(120, PlatformSettings::int('quests.description_min', 150));
    }

    private function wordCount(string $plain): int
    {
        if ($plain === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? count($words) : 0;
    }

    private function hasDeliverablesSignal(string $html, string $plain): bool
    {
        if (preg_match('/<(ul|ol)\b/i', $html) && preg_match('/<li\b/i', $html)) {
            return true;
        }

        if (preg_match('/\b(deliverable|deliverables|scope of work|you will receive|expected output|what i need|what you will|milestones?)\b/i', $plain)) {
            return true;
        }

        return $this->wordCount($plain) >= max(60, (int) ceil($this->minDescriptionChars() / 3));
    }

    /**
     * @return list<array{field: string, code: string, message: string}>
     */
    private function offPlatformIssues(string $text): array
    {
        $hits = $this->scanner->scan($text);
        $issues = [];

        foreach ($hits as $hit) {
            if (! in_array($hit['category'], [
                ConversationFlagCategory::OffPlatformPayment,
                ConversationFlagCategory::ExternalContact,
            ], true)) {
                continue;
            }

            $issues[] = [
                'field' => 'description',
                'code' => 'off_platform_contact',
                'message' => $hit['category'] === ConversationFlagCategory::OffPlatformPayment
                    ? __('Remove off-platform payment details (account numbers, payment links). Keep all payments on HustleSafe escrow.')
                    : __('Remove external contact details (phone numbers, social handles). Use HustleSafe messaging once your quest is live.'),
            ];
        }

        return $this->dedupeIssues($issues);
    }

    /**
     * @param  list<array{field: string, code: string, message: string}>  $issues
     * @return list<array{field: string, code: string, message: string}>
     */
    private function dedupeIssues(array $issues): array
    {
        $seen = [];
        $out = [];
        foreach ($issues as $issue) {
            $key = $issue['code'].'|'.$issue['field'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $issue;
        }

        return $out;
    }
}
