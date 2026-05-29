<?php

namespace App\Http\Requests\Operations;

use App\Enums\StaffLeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'admin';
    }

    public function rules(): array
    {
        return [
            'leave_type' => ['required', Rule::in(array_map(static fn (StaffLeaveType $t) => $t->value, StaffLeaveType::cases()))],
            'duration_type' => ['required', Rule::in(['full_day', 'hours', 'multiple_days'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_if:duration_type,multiple_days'],
            'hours_requested' => ['nullable', 'integer', 'min:1', 'max:23', 'required_if:duration_type,hours'],
            'reason' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
