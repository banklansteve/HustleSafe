<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'featured_tiers' => ['required', 'array'],
            'featured_tiers.*.label' => ['required', 'string', 'max:120'],
            'featured_tiers.*.durations' => ['required', 'array'],
            'featured_tiers.*.durations.*' => ['integer', 'min:1', 'max:90'],
            'featured_tiers.*.prices_minor' => ['required', 'array'],
            'featured_tiers.*.prices_minor.*' => ['integer', 'min:0'],
            'featured_tiers.*.placements' => ['required', 'array'],
            'referral_program' => ['required', 'array'],
            'referral_program.reward_type' => ['required', 'string', 'max:40'],
            'referral_program.client_reward_minor' => ['required', 'integer', 'min:0'],
            'referral_program.freelancer_reward_minor' => ['required', 'integer', 'min:0'],
            'referral_program.qualifying_event' => ['required', 'string', 'max:80'],
            'referral_program.reward_expiry_days' => ['required', 'integer', 'min:1', 'max:730'],
        ];
    }
}
