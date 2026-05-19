<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderQuestCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:quest_categories,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:65000'],
            'confirm_move' => ['sometimes', 'boolean'],
        ];
    }
}
