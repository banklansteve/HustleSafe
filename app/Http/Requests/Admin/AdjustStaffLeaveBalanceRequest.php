<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStaffLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'leave_type' => ['required', Rule::in(['annual', 'sick', 'emergency', 'unpaid'])],
            'mode' => ['required', Rule::in(['allocate', 'adjust'])],
            'adjustment_direction' => ['required_if:mode,adjust', 'nullable', Rule::in(['add', 'remove'])],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'adjustment_direction.required_if' => 'Choose whether to add to or remove from the leave balance.',
        ];
    }
}
