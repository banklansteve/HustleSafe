<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffPayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'in:bonus,deduction'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:2000'],
            'effective_date' => ['required', 'date'],
            'is_recurring' => ['nullable', 'boolean'],
            'reference' => ['nullable', 'string', 'max:190'],
        ];
    }
}
