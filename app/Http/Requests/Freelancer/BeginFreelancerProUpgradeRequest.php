<?php

namespace App\Http\Requests\Freelancer;

use App\Enums\SubscriptionBillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BeginFreelancerProUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'freelancer';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billing_cycle' => ['required', Rule::in([SubscriptionBillingCycle::Month->value, SubscriptionBillingCycle::Year->value])],
        ];
    }
}
