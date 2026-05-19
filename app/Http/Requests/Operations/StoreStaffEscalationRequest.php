<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffEscalationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', 'max:80'],
            'subject_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'recommendation' => ['required', 'string', 'min:15', 'max:3000'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'context_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
