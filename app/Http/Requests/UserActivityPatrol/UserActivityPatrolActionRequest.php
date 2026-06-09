<?php

namespace App\Http\Requests\UserActivityPatrol;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserActivityPatrolActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'flag_id' => ['nullable', 'integer', 'exists:user_activity_patrol_flags,id'],
            'warning_type' => ['nullable', 'string', 'max:64'],
            'severity' => ['nullable', 'string', Rule::in(['informal', 'formal', 'final', 'low', 'medium', 'high', 'critical'])],
            'message' => ['nullable', 'string', 'max:1000'],
            'subject' => ['nullable', 'string', 'max:200'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', Rule::in(['14d', '30d', '90d', 'indefinite', '7d', '30d', 'permanent'])],
            'title' => ['nullable', 'string', 'max:200'],
            'assign_to' => ['nullable', 'integer', 'exists:users,id'],
            'body' => ['nullable', 'string', 'min:10', 'max:2000'],
            'include_action_notice' => ['nullable', 'boolean'],
            'escrow_id' => ['nullable', 'integer', 'exists:payment_escrows,id'],
            'reverse_type' => ['nullable', 'string', Rule::in(['full', 'partial', 'chargeback'])],
            'partial_amount_minor' => ['nullable', 'integer', 'min:1'],
            'suspend_account' => ['nullable', 'boolean'],
            'secondary_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sanction_type' => ['nullable', 'string', Rule::in(['restriction', 'suspension', 'ban'])],
        ];
    }
}
