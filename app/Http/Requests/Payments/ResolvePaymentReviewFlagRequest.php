<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolvePaymentReviewFlagRequest extends FormRequest
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
            'action' => ['required', Rule::in(['reviewed', 'escalate', 'dismiss'])],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
