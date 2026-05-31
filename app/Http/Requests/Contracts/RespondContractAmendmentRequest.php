<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class RespondContractAmendmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:accept,decline'],
            'response_note' => ['required_if:action,decline', 'nullable', 'string', 'max:2000'],
        ];
    }
}
