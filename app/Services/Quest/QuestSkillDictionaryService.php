<?php

namespace App\Services\Quest;

use App\Models\QuestCategory;

final class QuestSkillDictionaryService
{
    /**
     * @param  list<string>  $exclude
     * @return list<string>
     */
    public function suggest(?int $leafCategoryId, string $query, array $exclude = [], int $limit = 20): array
    {
        return $this->suggestFromPool(
            $this->poolForCategory($leafCategoryId),
            $query,
            $exclude,
            $limit,
        );
    }

    /**
     * @param  list<int>  $leafCategoryIds
     * @param  list<string>  $exclude
     * @return list<string>
     */
    public function suggestForCategories(array $leafCategoryIds, string $query, array $exclude = [], int $limit = 20): array
    {
        return $this->suggestFromPool(
            $this->poolForCategories($leafCategoryIds),
            $query,
            $exclude,
            $limit,
        );
    }

    /**
     * @param  list<int>  $leafCategoryIds
     * @return list<string>
     */
    public function poolForCategories(array $leafCategoryIds): array
    {
        $leafCategoryIds = array_values(array_filter(array_map('intval', $leafCategoryIds)));

        if ($leafCategoryIds === []) {
            return $this->dedupePreserveCase(array_merge(
                config('quest_skill_dictionary.common', []),
                ...array_values(config('quest_skill_dictionary.by_parent', [])),
            ));
        }

        $pool = [];
        foreach ($leafCategoryIds as $leafCategoryId) {
            $pool = array_merge($pool, $this->poolForCategory($leafCategoryId));
        }

        return $this->dedupePreserveCase($pool);
    }

    /**
     * Resolve user input to the canonical dictionary label (case-insensitive).
     *
     * @param  list<int>  $leafCategoryIds
     */
    public function resolveCanonical(string $input, array $leafCategoryIds): ?string
    {
        $needle = $this->normalize($input);
        if ($needle === '') {
            return null;
        }

        foreach ($this->poolForCategories($leafCategoryIds) as $skill) {
            if ($this->normalize($skill) === $needle) {
                return $skill;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $inputs
     * @param  list<int>  $leafCategoryIds
     * @return list<string>
     */
    public function resolveMany(array $inputs, array $leafCategoryIds): array
    {
        $seen = [];
        $resolved = [];

        foreach ($inputs as $input) {
            $canonical = $this->resolveCanonical((string) $input, $leafCategoryIds);
            if ($canonical === null) {
                continue;
            }

            $key = $this->normalize($canonical);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $resolved[] = $canonical;
        }

        return $resolved;
    }

    /**
     * @param  list<string>  $pool
     * @param  list<string>  $exclude
     * @return list<string>
     */
    private function suggestFromPool(array $pool, string $query, array $exclude, int $limit): array
    {
        $excludeLower = collect($exclude)
            ->map(fn (string $s) => $this->normalize($s))
            ->filter()
            ->flip()
            ->all();

        $needle = $this->normalize($query);

        $candidates = collect($pool)
            ->unique(fn (string $skill) => $this->normalize($skill))
            ->reject(fn (string $skill) => isset($excludeLower[$this->normalize($skill)]))
            ->values();

        if ($needle !== '') {
            $candidates = $candidates
                ->filter(fn (string $skill) => str_contains($this->normalize($skill), $needle))
                ->sortBy(fn (string $skill) => $this->rank($skill, $needle))
                ->values();
        }

        return $candidates->take($limit)->values()->all();
    }

    /**
     * @return list<string>
     */
    public function poolForCategory(?int $leafCategoryId): array
    {
        $common = config('quest_skill_dictionary.common', []);
        $byParent = config('quest_skill_dictionary.by_parent', []);
        $byLeaf = config('quest_skill_dictionary.by_leaf', []);

        if ($leafCategoryId === null || $leafCategoryId < 1) {
            return $this->dedupePreserveCase(array_merge($common, ...array_values($byParent)));
        }

        $leaf = QuestCategory::query()->with('parent:id,slug')->find($leafCategoryId);
        if ($leaf === null) {
            return $this->dedupePreserveCase(array_merge($common, ...array_values($byParent)));
        }

        $parentSlug = $leaf->parent?->slug;
        $leafSlug = $leaf->slug;

        $parentSkills = $parentSlug ? ($byParent[$parentSlug] ?? []) : [];
        $leafSkills = $leafSlug ? ($byLeaf[$leafSlug] ?? []) : [];

        return $this->dedupePreserveCase(array_merge($leafSkills, $parentSkills, $common));
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private function rank(string $skill, string $needle): int
    {
        $normalized = $this->normalize($skill);

        if ($normalized === $needle) {
            return 0;
        }

        if (str_starts_with($normalized, $needle)) {
            return 1;
        }

        return 2;
    }

    /**
     * @param  list<string>  $skills
     * @return list<string>
     */
    private function dedupePreserveCase(array $skills): array
    {
        $seen = [];
        $out = [];

        foreach ($skills as $skill) {
            $label = trim((string) $skill);
            if ($label === '') {
                continue;
            }
            $key = $this->normalize($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $label;
        }

        return $out;
    }
}
