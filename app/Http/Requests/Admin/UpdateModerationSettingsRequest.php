<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModerationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'new_account_review_hours' => ['required', 'integer', 'min:24', 'max:168'],
            'allowed_external_domains' => ['required', 'array'],
            'allowed_external_domains.*' => ['string', 'max:120'],
            'cloudinary_moderation_enabled' => ['sometimes', 'boolean'],
            'templates' => ['sometimes', 'array'],
            'templates.*.key' => ['required_with:templates', 'string', 'max:120'],
            'templates.*.subject' => ['nullable', 'string', 'max:160'],
            'templates.*.body' => ['required_with:templates', 'string', 'max:5000'],
        ];
    }
}
