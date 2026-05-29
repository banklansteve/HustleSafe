<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffComplianceCaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:open,under_review,resolved,escalated'],
            'note' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
