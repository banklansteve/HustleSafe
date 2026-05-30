<?php

namespace App\Http\Requests\Operations;

use App\Enums\StaffLeaveType;
use App\Support\Hr\StaffLeaveRequestDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $staff = $this->user();
            if ($staff === null) {
                return;
            }

            $durationType = (string) $this->input('duration_type');
            $leaveType = (string) $this->input('leave_type');
            $startDate = (string) $this->input('start_date');
            $endDate = $this->input('end_date');

            $days = StaffLeaveRequestDuration::calculateDays($durationType, $startDate, is_string($endDate) ? $endDate : null);
            $year = StaffLeaveRequestDuration::calendarYear($startDate);
            $snapshot = StaffLeaveRequestDuration::balanceSnapshot((int) $staff->id, $leaveType, $year);

            if ($days > $snapshot['effective']) {
                $validator->errors()->add(
                    'leave_type',
                    StaffLeaveRequestDuration::insufficientBalanceMessage(
                        $leaveType,
                        $year,
                        $days,
                        $snapshot['effective'],
                        $snapshot['balance'],
                    ),
                );
            }
        });
    }
}
