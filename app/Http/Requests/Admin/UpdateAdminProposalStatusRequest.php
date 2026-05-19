<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminProposalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminProposalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'admin_status' => ['required', Rule::in(AdminProposalStatus::values())],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'notify_freelancer' => ['sometimes', 'boolean'],
            'notify_client' => ['sometimes', 'boolean'],
            'notification_preview' => ['nullable', 'string', 'max:2000'],
            'referred_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
