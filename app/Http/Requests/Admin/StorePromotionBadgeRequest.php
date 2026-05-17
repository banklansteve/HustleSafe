<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePromotionBadgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:1000'],
            'criteria' => ['nullable', 'array'],
            'is_automatic' => ['sometimes', 'boolean'],
            'requires_manual_review' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'is_time_limited' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name')) {
            $this->merge(['slug' => Str::slug((string) $this->input('name'))]);
        }
    }
}
