<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffRoleGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStaffRoleGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'role_groups' => ['required', 'array', 'min:1'],
            'role_groups.*' => ['required', 'string', 'distinct', Rule::in(StaffRoleGroup::values())],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
