<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationMonitoringTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'term_type' => ['required', Rule::in(['abusive_blacklist', 'custom_keyword'])],
            'pattern' => ['required', 'string', 'max:200'],
            'is_wildcard' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'locale_hint' => ['nullable', 'string', 'max:24'],
        ];
    }
}
