<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffPayrollDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'wef_month' => ['required', 'integer', 'between:1,12'],
            'wef_year' => ['required', 'integer', 'between:2020,2100'],
            'deduction_mode' => ['required', Rule::in(['flat', 'percentage'])],
            'amount' => ['nullable', 'numeric', 'gt:0', 'required_if:deduction_mode,flat'],
            'deduction_basis' => ['nullable', Rule::in(['total_pay', 'basic_salary', 'custom_amount']), 'required_if:deduction_mode,percentage'],
            'deduction_percentage' => ['nullable', 'numeric', 'gt:0', 'max:100', 'required_if:deduction_mode,percentage'],
            'deduction_custom_base_amount' => ['nullable', 'numeric', 'gt:0', 'required_if:deduction_basis,custom_amount'],
        ];
    }
}
