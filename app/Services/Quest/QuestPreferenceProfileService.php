<?php

namespace App\Services\Quest;

use App\Models\QuestCategory;
use Illuminate\Support\Str;

class QuestPreferenceProfileService
{
    /**
     * @return array{
     *   profile_type: string,
     *   profile_label: string,
     *   profile_hint: string,
     *   show_preferences: bool,
     *   catch_all_message: ?string,
     *   fields: array<string, array<string, mixed>>,
     *   show_weather_impact: bool,
     *   show_confidentiality: bool,
     *   show_data_handling: bool,
     *   parent_slug: ?string,
     *   leaf_slug: ?string,
     * }
     */
    public function profileForLeafCategoryId(?int $leafId): array
    {
        if ($leafId === null || $leafId < 1) {
            return $this->emptyProfile();
        }

        $leaf = QuestCategory::query()
            ->with('parent:id,slug,name')
            ->find($leafId);

        if ($leaf === null || $leaf->parent_id === null) {
            return $this->emptyProfile();
        }

        $parentSlug = (string) ($leaf->parent?->slug ?? '');
        $leafSlug = (string) $leaf->slug;
        $haystack = Str::lower($leaf->name.' '.$leaf->parent?->name.' '.$parentSlug.' '.$leafSlug);

        $type = $this->resolveProfileType($parentSlug, $leafSlug, $haystack);
        if ($type === 'none') {
            return [
                'profile_type' => 'none',
                'profile_label' => '',
                'profile_hint' => '',
                'show_preferences' => false,
                'catch_all_message' => __('This category doesn\'t have standard preference fields. You can communicate specific needs in the job description.'),
                'fields' => [],
                'show_weather_impact' => false,
                'show_confidentiality' => false,
                'show_data_handling' => false,
                'parent_slug' => $parentSlug,
                'leaf_slug' => $leafSlug,
            ];
        }

        $profiles = config('quest_preference_profiles.profiles', []);
        $definition = $profiles[$type] ?? [];
        $fields = $definition['fields'] ?? [];

        $showWeather = $type === 'physical' && in_array($leafSlug, config('quest_preference_profiles.outdoor_weather_leaves', []), true);
        $showConfidentiality = $type === 'professional' && in_array($parentSlug, config('quest_preference_profiles.sensitive_confidentiality_parents', []), true);
        $showDataHandling = $type === 'professional' && in_array($parentSlug, config('quest_preference_profiles.data_handling_parents', []), true);

        $fields = $this->filterConditionalFields($fields, $showWeather, $showConfidentiality, $showDataHandling);

        return [
            'profile_type' => $type,
            'profile_label' => (string) ($definition['label'] ?? 'Your preferences'),
            'profile_hint' => (string) ($definition['hint'] ?? ''),
            'show_preferences' => true,
            'catch_all_message' => null,
            'fields' => $fields,
            'show_weather_impact' => $showWeather,
            'show_confidentiality' => $showConfidentiality,
            'show_data_handling' => $showDataHandling,
            'parent_slug' => $parentSlug,
            'leaf_slug' => $leafSlug,
        ];
    }

    protected function resolveProfileType(string $parentSlug, string $leafSlug, string $haystack): string
    {
        foreach (['technical', 'physical', 'design', 'lessons', 'care', 'logistics', 'professional'] as $type) {
            if ($this->matchesDetection($type, $parentSlug, $leafSlug, $haystack)) {
                return $type;
            }
        }

        return 'none';
    }

    protected function matchesDetection(string $type, string $parentSlug, string $leafSlug, string $haystack): bool
    {
        $rules = config("quest_preference_profiles.detection.{$type}", []);

        if (in_array($parentSlug, $rules['parents'] ?? [], true)) {
            return true;
        }

        if (in_array($leafSlug, $rules['leaves'] ?? [], true)) {
            return true;
        }

        foreach ($rules['keywords'] ?? [] as $keyword) {
            if ($keyword !== '' && str_contains($haystack, Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, array<string, mixed>>
     */
    protected function filterConditionalFields(array $fields, bool $showWeather, bool $showConfidentiality, bool $showDataHandling): array
    {
        return collect($fields)->filter(function (array $field, string $key) use ($showWeather, $showConfidentiality, $showDataHandling) {
            $conditional = $field['conditional'] ?? null;
            if ($conditional === 'outdoor_only') {
                return $showWeather;
            }
            if ($conditional === 'sensitive_only') {
                return $showConfidentiality;
            }
            if ($conditional === 'data_handling') {
                return $showDataHandling;
            }

            return true;
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyProfile(): array
    {
        return [
            'profile_type' => 'none',
            'profile_label' => '',
            'profile_hint' => '',
            'show_preferences' => false,
            'catch_all_message' => null,
            'fields' => [],
            'show_weather_impact' => false,
            'show_confidentiality' => false,
            'show_data_handling' => false,
            'parent_slug' => null,
            'leaf_slug' => null,
        ];
    }
}
