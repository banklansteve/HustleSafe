<?php

namespace App\Http\Requests\Quests;

use App\Models\Quest;
use App\Services\Quest\QuestPreferenceService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $quest instanceof Quest && ($this->user()?->can('update', $quest) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $quest = $this->route('quest');
        if (! $quest instanceof Quest) {
            return;
        }

        $data = app(QuestPreferenceService::class)->normalizeSubmittedPayload(
            ['preferences' => $this->input('preferences', [])],
            (int) $quest->quest_category_id,
        );

        $this->merge($data);
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['nullable', 'array'],
        ];
    }
}
