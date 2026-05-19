<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminComplianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'request_type' => ['required', Rule::in(['data_export', 'data_deletion', 'access_log_review', 'retention_exception'])],
            'requester_note' => ['nullable', 'string', 'max:3000'],
            'assigned_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
