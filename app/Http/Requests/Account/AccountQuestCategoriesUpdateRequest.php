<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountQuestCategoriesUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'freelancer';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'quest_category_ids' => ['required', 'array', 'min:1', 'max:40'],
            'quest_category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('quest_categories', 'id')->whereNotNull('parent_id')->where('is_active', true)->where('status', 'active'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quest_category_ids.required' => __('Pick at least one work subcategory so we can match you to quests.'),
            'quest_category_ids.min' => __('Pick at least one work subcategory so we can match you to quests.'),
        ];
    }
}
