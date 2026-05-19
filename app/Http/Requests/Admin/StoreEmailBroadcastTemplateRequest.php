<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailBroadcastTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:140'],
            'category' => ['required', 'string', 'max:100'],
            'suggested_audience' => ['nullable', 'string', 'max:180'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'preview_text' => ['nullable', 'string', 'max:180'],
            'body_html' => ['required', 'string', 'min:20', 'max:120000'],
        ];
    }
}
