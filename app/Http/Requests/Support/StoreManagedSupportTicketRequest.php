<?php

namespace App\Http\Requests\Support;

use App\Services\Support\SupportTicketManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array((string) $this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:180'],
            'issue_group' => ['required', 'string', 'max:100'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['required', 'string', 'max:50000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'action_items' => ['nullable', 'array', 'max:20'],
            'action_items.*.label' => ['required_with:action_items', 'string', 'max:500'],
            'action_items.*.completed' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120'],
        ];
    }
}
