<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkQuestCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:quest_categories,id'],
            'action' => ['required', Rule::in(['status', 'parent', 'fees'])],
            'status' => ['nullable', Rule::in(['active', 'hidden', 'archived']), 'required_if:action,status'],
            'parent_id' => ['nullable', 'integer', 'exists:quest_categories,id', 'required_if:action,parent'],
            'uses_fee_override' => ['sometimes', 'boolean'],
            'client_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:50', 'required_if:action,fees'],
            'freelancer_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:50', 'required_if:action,fees'],
            'confirm' => ['accepted'],
        ];
    }
}
