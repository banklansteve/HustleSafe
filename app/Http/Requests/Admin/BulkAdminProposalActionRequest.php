<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminProposalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAdminProposalActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:quest_offers,id'],
            'action' => ['required', Rule::in(['change_status', 'flag', 'refer', 'restrict', 'suspend', 'post_notice'])],
            'admin_status' => ['nullable', Rule::in(AdminProposalStatus::values())],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'type' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'body' => ['nullable', 'string', 'max:2000'],
            'referred_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
