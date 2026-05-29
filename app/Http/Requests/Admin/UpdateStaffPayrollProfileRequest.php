<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffPayrollProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'wef_month' => ['required', 'integer', 'between:1,12'],
            'wef_year' => ['required', 'integer', 'between:2020,2100'],
            'bank_name' => ['nullable', 'string', 'max:190'],
            'bank_account_name' => ['nullable', 'string', 'max:190'],
            'bank_account_number' => ['nullable', 'string', 'size:11'],
        ];
    }
}
