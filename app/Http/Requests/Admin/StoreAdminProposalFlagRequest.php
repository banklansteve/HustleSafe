<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminProposalFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['off_platform_contact', 'solicitation', 'lowball_bid', 'copy_paste', 'velocity_spam', 'coordinated_bidding', 'high_value_low_tier', 'prior_admin_actions', 'policy_violation', 'other'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assigned_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_group' => ['nullable', Rule::in(['all_moderation_admins', 'all_finance_admins', 'all_super_admins'])],
            'description' => ['required', 'string', 'min:30', 'max:2000'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'visibility_impact' => ['nullable', Rule::in(['none', 'restrict_acceptance', 'hide_pending_resolution'])],
            'notify_freelancer' => ['sometimes', 'boolean'],
            'notify_client' => ['sometimes', 'boolean'],
        ];
    }
}
