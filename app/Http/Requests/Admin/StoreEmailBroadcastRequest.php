<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'template_id' => ['nullable', 'integer', 'exists:email_broadcast_templates,id'],
            'audience' => ['required', 'array'],
            'audience.groups' => ['nullable', 'array'],
            'audience.groups.*' => ['string', Rule::in(['all_users', 'clients', 'freelancers', 'admins', 'super_admins'])],
            'audience.state_ids' => ['nullable', 'array'],
            'audience.state_ids.*' => ['integer', 'exists:states,id'],
            'audience.category_ids' => ['nullable', 'array'],
            'audience.category_ids.*' => ['integer', 'exists:quest_categories,id'],
            'audience.verification_tiers' => ['nullable', 'array'],
            'audience.verification_tiers.*' => ['string', 'max:40'],
            'audience.account_status' => ['nullable', Rule::in(['active', 'suspended', 'pending_verification'])],
            'audience.activity' => ['nullable', Rule::in(['active_30', 'inactive_60', 'completed_contract', 'never_started_contract'])],
            'mode' => ['nullable', Rule::in(['template', 'custom'])],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'preview_text' => ['nullable', 'string', 'max:180'],
            'reply_to' => ['nullable', 'email', 'max:180'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'body_html' => ['required', 'string', 'min:20', 'max:120000'],
            'send_mode' => ['required', Rule::in(['now', 'schedule'])],
            'scheduled_for' => ['nullable', 'required_if:send_mode,schedule', 'date', 'after:now'],
        ];
    }
}
