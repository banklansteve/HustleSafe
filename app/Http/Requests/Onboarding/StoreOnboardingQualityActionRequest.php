<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingQualityActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::in([
                    'approve',
                    'nudge',
                    'request_verification',
                    'flag_monitoring',
                    'clear_monitoring',
                    'escalate',
                    'resolve_escalation',
                    'suspend',
                    'lift_suspension',
                    'override_flags',
                    're_evaluate',
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'template_key' => ['nullable', 'string', 'max:80'],
            'flag_overrides' => ['nullable', 'array'],
            'flag_overrides.*.dismissed' => ['nullable', 'boolean'],
        ];
    }
}
