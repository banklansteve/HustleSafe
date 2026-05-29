<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffComplianceCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'severity' => ['required', 'string', 'in:minor,serious,gross_misconduct'],
            'incident_note' => ['required', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array'],
        ];
    }
}
