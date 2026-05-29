<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuspiciousActivityFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'pattern' => ['required', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:3000'],
            'staff_session_log_id' => ['nullable', 'integer', 'exists:staff_session_logs,id'],
        ];
    }
}
