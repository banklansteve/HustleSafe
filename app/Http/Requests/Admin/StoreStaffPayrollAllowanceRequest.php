<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffPayrollAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:190'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'wef_month' => ['required', 'integer', 'between:1,12'],
            'wef_year' => ['required', 'integer', 'between:2020,2100'],
        ];
    }
}
