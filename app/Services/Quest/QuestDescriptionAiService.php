<?php

namespace App\Services\Quest;

use App\Models\QuestCategory;
use App\Services\AI\ClaudeClient;
use Throwable;

/**
 * Quest description suggestions for the create wizard.
 *
 * Uses Claude when ANTHROPIC_API_KEY is set; otherwise falls back to in-app
 * category-aware templates (no external service required).
 */
class QuestDescriptionAiService
{
    public function __construct(
        private readonly ClaudeClient $claude,
        private readonly QuestDescriptionTemplateService $templates,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function usesClaude(): bool
    {
        return $this->claude->isConfigured();
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    public function suggestForQuest(string $title, ?int $categoryId, ?string $notes = null): array
    {
        if ($this->usesClaude()) {
            try {
                $suggestions = $this->suggestViaClaude($title, $categoryId, $notes);
                if ($suggestions !== []) {
                    return $suggestions;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->templates->suggest($title, $categoryId);
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function suggestViaClaude(string $title, ?int $categoryId, ?string $notes = null): array
    {
        $category = $categoryId !== null && $categoryId > 0
            ? QuestCategory::query()->with('parent:id,name')->find($categoryId)
            : null;

        $parentName = $category?->parent?->name ?? ($category?->parent_id === null ? $category?->name : null);
        $leafName = $category?->parent_id !== null ? $category?->name : null;

        $context = collect([
            $title !== '' ? 'Quest title: '.$title : null,
            $parentName ? 'Category: '.$parentName : null,
            $leafName ? 'Subcategory: '.$leafName : null,
            $notes ? 'Extra client notes: '.$notes : null,
        ])->filter()->implode("\n");

        $text = $this->claude->prompt($this->systemPrompt(), $context, [
            'temperature' => 0.7,
            'max_tokens' => 1600,
        ]);

        return $this->parseSuggestions($text);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You write quest (job) description drafts for HustleSafe, a Nigerian services and freelancing marketplace.
        The client will edit your draft. Category, location, budget, schedule, materials, site access, and preference fields are collected in separate form steps — do NOT repeat them.

        Focus ONLY on:
        - What work needs to be done (specific tasks, areas, deliverables)
        - Scope: clearly state in-scope vs out-of-scope where helpful
        - Current situation / context that affects how the work is done
        - Quality standards and success criteria (how the client will judge "done")
        - Category-relevant detail (e.g. creative direction for design, acceptance criteria for tech, care routines for childcare — not logistics)

        Do NOT mention or prompt for:
        - Category, subcategory, or repeating the quest title as a header
        - Address, city, LGA, where the service happens, pickup/drop-off locations
        - Budget, fees, ₦ amounts, start dates, deadlines, or scheduling
        - Who provides materials/supplies/tools, vehicle type, or site access
        - Revision rounds, NDA, or preference fields already on the form

        Style:
        - Nigerian context where natural; plain text only (no markdown headings or code fences)
        - Polished, professional, ready to paste — use short paragraphs and "- " bullets where useful
        - Concise = 2–4 sentences; Detailed = fuller scope with bullets; Structured = labelled sections with bullets
        - Use [bracket placeholders] only for facts only the client knows; keep surrounding prose polished
        - Realistic for low-to-medium local tasks, not industrial scale

        Output STRICT JSON only — an array of exactly 3 objects:
        [{"label": "Concise", "text": "..."}, {"label": "Detailed", "text": "..."}, {"label": "Structured", "text": "..."}]
        No prose before or after the JSON.
        PROMPT;
    }

    /**
     * @return list<array{label: string, text: string}>
     */
    private function parseSuggestions(string $raw): array
    {
        $clean = trim($raw);
        $clean = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $clean) ?? $clean;

        if (preg_match('/\[[\s\S]*\]/', $clean, $m) === 1) {
            $clean = $m[0];
        }

        $decoded = json_decode(trim($clean), true);
        $out = [];

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $text = isset($item['text']) ? trim((string) $item['text']) : '';
                if ($text === '') {
                    continue;
                }
                $out[] = [
                    'label' => trim((string) ($item['label'] ?? 'Suggestion')) ?: 'Suggestion',
                    'text' => $text,
                ];
            }
        }

        if ($out === [] && trim($raw) !== '') {
            $out[] = ['label' => 'Suggestion', 'text' => trim($raw)];
        }

        return array_slice($out, 0, 3);
    }
}
