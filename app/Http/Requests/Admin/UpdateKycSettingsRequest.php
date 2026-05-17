<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKycSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'active_provider' => ['required', 'string', 'max:40'],
            'fallback_provider' => ['nullable', 'string', 'max:40'],
            'thresholds' => ['required', 'array'],
            'thresholds.nin' => ['required', 'integer', 'min:50', 'max:100'],
            'thresholds.bvn' => ['required', 'integer', 'min:50', 'max:100'],
            'thresholds.face_similarity' => ['required', 'integer', 'min:50', 'max:100'],
            'feature_gates' => ['required', 'array'],
            'feature_gates.*' => ['integer', 'min:0', 'max:5'],
            'resubmission_limit' => ['required', 'integer', 'min:1', 'max:10'],
            'verification_fees' => ['required', 'array'],
            'verification_fees.enabled' => ['boolean'],
            'verification_fees.cac_fee_minor' => ['required', 'integer', 'min:0'],
            'limits' => ['required', 'array'],
            'limits.*' => ['integer', 'min:0'],
        ];
    }
}
