<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        $isSubcategory = $this->filled('parent_id');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'name' => ['required', 'string', 'max:'.($isSubcategory ? 60 : 50)],
            'slug' => [
                'required',
                'string',
                'max:96',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('quest_categories', 'slug')
                    ->where(fn ($q) => $q->where('parent_id', $this->input('parent_id')))
                    ->ignore($this->route('category')),
            ],
            'description' => ['nullable', 'string', 'max:'.($isSubcategory ? 200 : 150)],
            'icon_name' => ['nullable', 'string', 'max:80'],
            'icon_color' => ['nullable', 'string', 'max:32'],
            'status' => ['required', Rule::in(['active', 'hidden', 'draft', 'archived'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'uses_fee_override' => ['sometimes', 'boolean'],
            'client_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'freelancer_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'budget_guardrails_enabled' => ['sometimes', 'boolean'],
            'min_budget_minor' => ['nullable', 'integer', 'min:0', 'required_if:budget_guardrails_enabled,true'],
            'max_budget_minor' => ['nullable', 'integer', 'gt:min_budget_minor'],
            'high_value_approval_enabled' => ['sometimes', 'boolean'],
            'high_value_threshold_minor' => ['nullable', 'integer', 'min:0', 'required_if:high_value_approval_enabled,true'],
            'acknowledge_name_impact' => ['sometimes', 'boolean'],
            'acknowledge_fee_impact' => ['sometimes', 'boolean'],
        ];
    }
}
