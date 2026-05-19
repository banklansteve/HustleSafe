<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:500'],
            'link_url' => ['nullable', 'url', 'max:1000'],
            'link_text' => ['nullable', 'string', 'max:120', 'required_with:link_url'],
            'color' => ['required', Rule::in(['info', 'success', 'warning', 'alert', 'brand'])],
            'segment' => ['required', Rule::in(['all', 'clients', 'freelancers', 'unverified'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'dismissible' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'archived'])],
        ];
    }
}
