<?php

namespace App\Services\Quest;

use App\Models\Quest;
use App\Models\QuestPreference;
use Illuminate\Support\Arr;

class QuestPreferenceService
{
    public function __construct(
        protected QuestPreferenceProfileService $profiles,
    ) {}

    /**
     * @param  array<string, mixed>  $submitted  keyed by preference_key
     */
    public function syncForQuest(Quest $quest, array $submitted, ?int $categoryId = null, bool $alwaysTouchUpdated = false): void
    {
        $categoryId ??= (int) $quest->quest_category_id;
        $profile = $this->profiles->profileForLeafCategoryId($categoryId);

        if (empty($profile['show_preferences'])) {
            $quest->preferences()->delete();
            $quest->forceFill(['preferences_last_updated' => null])->save();

            return;
        }

        $fieldKeys = array_keys($profile['fields'] ?? []);
        $hasAnySpecified = false;

        foreach ($fieldKeys as $key) {
            $raw = $submitted[$key] ?? null;
            $isSpecified = $this->valueIsSpecified($raw, $profile['fields'][$key] ?? []);
            if ($isSpecified) {
                $hasAnySpecified = true;
            }

            QuestPreference::query()->updateOrCreate(
                ['quest_id' => $quest->id, 'preference_key' => $key],
                [
                    'preference_value' => $this->normalizeValue($raw, $profile['fields'][$key] ?? []),
                    'is_specified' => $isSpecified,
                ],
            );
        }

        if ($alwaysTouchUpdated || $hasAnySpecified) {
            $quest->forceFill(['preferences_last_updated' => now()])->save();
        }
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   display_value: string,
     *   is_specified: bool,
     *   id: int,
     * }>
     */
    public function displayListForQuest(Quest $quest): array
    {
        $profile = $this->profiles->profileForLeafCategoryId((int) $quest->quest_category_id);
        $fields = $profile['fields'] ?? [];
        $stored = $quest->relationLoaded('preferences')
            ? $quest->preferences->keyBy('preference_key')
            : $quest->preferences()->get()->keyBy('preference_key');

        $rows = [];
        foreach ($fields as $key => $definition) {
            $pref = $stored->get($key);
            $isSpecified = (bool) ($pref?->is_specified);
            $rows[] = [
                'id' => (int) ($pref?->id ?? 0),
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'display_value' => $isSpecified
                    ? $this->formatDisplayValue($pref?->preference_value, $definition)
                    : __('Not specified'),
                'is_specified' => $isSpecified,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesMapForQuest(Quest $quest): array
    {
        return $quest->preferences()
            ->get()
            ->mapWithKeys(fn (QuestPreference $p) => [$p->preference_key => $p->preference_value])
            ->all();
    }

    public function hasSpecifiedPreferences(Quest $quest): bool
    {
        return $quest->preferences()->where('is_specified', true)->exists();
    }

    /**
     * @param  mixed  $raw
     */
    protected function valueIsSpecified(mixed $raw, array $field): bool
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'checkbox_group') {
            return is_array($raw) && count(array_filter($raw)) > 0;
        }

        if ($type === 'number') {
            return $raw !== null && $raw !== '' && is_numeric($raw);
        }

        if ($type === 'radio') {
            $default = $field['default'] ?? 'not_specified';

            return is_string($raw) && $raw !== '' && $raw !== $default && $raw !== 'not_specified';
        }

        return is_string($raw) && trim($raw) !== '';
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>|string|null
     */
    protected function normalizeValue(mixed $raw, array $field): array|string|null
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'checkbox_group') {
            return is_array($raw) ? array_values(array_filter($raw)) : [];
        }

        if ($type === 'number') {
            return $raw === null || $raw === '' ? null : (int) $raw;
        }

        return is_string($raw) ? trim($raw) : $raw;
    }

    /**
     * @param  mixed  $value
     */
    public function formatDisplayValue(mixed $value, array $field): string
    {
        $type = $field['type'] ?? 'text';
        $options = $field['options'] ?? [];

        if ($type === 'checkbox_group' && is_array($value)) {
            return collect($value)
                ->map(fn ($k) => $options[$k] ?? $k)
                ->implode(', ');
        }

        if ($type === 'radio' && is_string($value)) {
            return (string) ($options[$value] ?? $value);
        }

        if ($type === 'number' && $value !== null) {
            return (string) $value;
        }

        return is_string($value) ? $value : (string) json_encode($value);
    }

    /**
     * Strip preference keys not in profile from request payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeSubmittedPayload(array $data, ?int $categoryId): array
    {
        $profile = $this->profiles->profileForLeafCategoryId($categoryId);
        if (empty($profile['show_preferences'])) {
            unset($data['preferences']);

            return $data;
        }

        $allowed = array_keys($profile['fields'] ?? []);
        $prefs = Arr::wrap($data['preferences'] ?? []);
        $data['preferences'] = array_intersect_key($prefs, array_flip($allowed));

        return $data;
    }
}
