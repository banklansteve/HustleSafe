<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondContractDeliveryExtensionRequest extends FormRequest
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
            'action' => ['required', Rule::in(['accept', 'decline', 'counter'])],
            'decline_reason' => ['required_if:action,decline', 'nullable', 'string', 'min:10', 'max:5000'],
            'counter_proposed_date' => ['required_if:action,counter', 'nullable', 'date', 'after:today'],
        ];
    }
}
