<?php

namespace App\Http\Requests\Admin\PremiumPatrol;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PremiumPatrolGrantPremiumRequest extends FormRequest
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
            'billing_cycle' => ['required', Rule::in(['month', 'year'])],
            'reason_code' => ['required', 'string', 'max:64'],
            'reason_notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
