<?php

namespace App\Http\Requests\Admin\PremiumPatrol;

use Illuminate\Foundation\Http\FormRequest;

class PremiumPatrolActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'max:64'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:2000'],
            'assign_to_id' => ['nullable', 'integer', 'exists:users,id'],
            'severity' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'watchlist_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'send_notification' => ['nullable', 'boolean'],
            'refund_amount_minor' => ['nullable', 'integer', 'min:0'],
            'refund_destination' => ['nullable', 'string', 'in:wallet,payment_method'],
        ];
    }
}
