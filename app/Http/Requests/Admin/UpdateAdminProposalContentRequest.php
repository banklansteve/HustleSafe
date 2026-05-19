<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminProposalContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'pitch' => ['required', 'string', 'min:20', 'max:12000'],
            'scope_detail' => ['nullable', 'string', 'max:12000'],
            'warranty_terms' => ['nullable', 'string', 'max:5000'],
            'quoted_amount_minor' => ['required', 'integer', 'min:0'],
            'estimated_duration_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'reason' => ['required', 'string', 'min:30', 'max:2000'],
            'notify_freelancer' => ['sometimes', 'boolean'],
            'notify_client' => ['sometimes', 'boolean'],
        ];
    }
}
