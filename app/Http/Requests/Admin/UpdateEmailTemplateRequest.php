<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*.type' => ['required', 'string', 'max:40'],
            'blocks.*.content' => ['nullable', 'string', 'max:12000'],
            'blocks.*.label' => ['nullable', 'string', 'max:120'],
            'blocks.*.url' => ['nullable', 'string', 'max:1000'],
            'theme' => ['nullable', 'array'],
            'theme.logo' => ['nullable', 'string', 'max:120'],
            'theme.primary_color' => ['nullable', 'string', 'max:40'],
            'theme.footer' => ['nullable', 'string', 'max:500'],
            'change_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
