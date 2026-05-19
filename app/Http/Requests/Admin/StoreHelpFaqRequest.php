<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHelpFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'help_section_id' => ['required', 'integer', 'exists:help_sections,id'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:20000'],
            'audience' => ['required', Rule::in(['all', 'clients', 'freelancers'])],
            'search_keywords' => ['nullable', 'array'],
            'search_keywords.*' => ['string', 'max:80'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'change_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
