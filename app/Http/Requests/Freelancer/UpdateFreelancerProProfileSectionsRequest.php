<?php

namespace App\Http\Requests\Freelancer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFreelancerProProfileSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role?->slug === 'freelancer'
            && app(\App\Services\Freelancer\FreelancerProSubscriptionService::class)->canUseCustomProfileSections($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $limits = config('freelancer_pro.pro_profile_sections', []);

        return [
            'testimonials' => ['nullable', 'array', 'max:'.(int) ($limits['testimonials']['max_items'] ?? 6)],
            'testimonials.*.quote' => ['required', 'string', 'max:500'],
            'testimonials.*.author' => ['nullable', 'string', 'max:120'],
            'testimonials.*.role' => ['nullable', 'string', 'max:120'],
            'external_links' => ['nullable', 'array', 'max:'.(int) ($limits['external_links']['max_items'] ?? 8)],
            'external_links.*.label' => ['required', 'string', 'max:80'],
            'external_links.*.url' => ['required', 'url', 'max:500'],
            'media_links' => ['nullable', 'array', 'max:'.(int) ($limits['media_links']['max_items'] ?? 12)],
            'media_links.*.label' => ['required', 'string', 'max:80'],
            'media_links.*.url' => ['required', 'url', 'max:500'],
        ];
    }
}
