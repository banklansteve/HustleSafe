<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffLeaveRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewStaffLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([StaffLeaveRequestStatus::Approved->value, StaffLeaveRequestStatus::Rejected->value])],
            'review_note' => ['required', 'string', 'max:3000'],
        ];
    }
}
