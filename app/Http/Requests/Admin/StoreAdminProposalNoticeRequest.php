<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminProposalNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['warning', 'informational', 'urgent', 'resolved'])],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'visible_to_freelancer' => ['sometimes', 'boolean'],
            'visible_to_client' => ['sometimes', 'boolean'],
            'notify_stakeholders' => ['sometimes', 'boolean'],
        ];
    }
}
