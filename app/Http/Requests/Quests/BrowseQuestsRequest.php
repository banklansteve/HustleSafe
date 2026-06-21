<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrowseQuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'freelancer';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'local_government_id' => ['nullable', 'integer', 'exists:local_governments,id'],
            'parent_category_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'quest_category_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:quest_categories,id'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'budget_min_ngn' => ['nullable', 'numeric', 'min:0'],
            'budget_max_ngn' => ['nullable', 'numeric', 'min:0'],
            'skill' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'string', Rule::in(['posted_desc', 'posted_asc', 'budget_desc', 'budget_asc', 'deadline_asc', 'match_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'cleared' => ['nullable', 'boolean'],
            'smart' => ['nullable', 'boolean'],
        ];
    }

    public function cleared(): bool
    {
        return $this->boolean('cleared');
    }

    public function smart(): bool
    {
        return $this->boolean('smart');
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'state_id' => isset($validated['state_id']) ? (int) $validated['state_id'] : null,
            'local_government_id' => isset($validated['local_government_id']) ? (int) $validated['local_government_id'] : null,
            'parent_category_id' => isset($validated['parent_category_id']) ? (int) $validated['parent_category_id'] : null,
            'quest_category_id' => isset($validated['quest_category_id']) ? (int) $validated['quest_category_id'] : null,
            'category_ids' => $categoryIds,
            'budget_min' => $this->budgetMinor($validated, 'budget_min', 'budget_min_ngn'),
            'budget_max' => $this->budgetMinor($validated, 'budget_max', 'budget_max_ngn'),
            'budget_min_ngn' => isset($validated['budget_min_ngn']) ? (float) $validated['budget_min_ngn'] : (isset($validated['budget_min']) ? ((int) $validated['budget_min']) / 100 : null),
            'budget_max_ngn' => isset($validated['budget_max_ngn']) ? (float) $validated['budget_max_ngn'] : (isset($validated['budget_max']) ? ((int) $validated['budget_max']) / 100 : null),
            'skill' => trim((string) ($validated['skill'] ?? '')),
            'sort' => (string) ($validated['sort'] ?? 'posted_desc'),
            'page' => max(1, (int) ($validated['page'] ?? 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function budgetMinor(array $validated, string $minorKey, string $ngnKey): ?int
    {
        if (isset($validated[$minorKey])) {
            return (int) $validated[$minorKey];
        }

        if (! isset($validated[$ngnKey]) || $validated[$ngnKey] === '') {
            return null;
        }

        return (int) round(((float) $validated[$ngnKey]) * 100);
    }
}
