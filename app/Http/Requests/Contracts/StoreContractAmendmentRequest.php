<?php

namespace App\Http\Requests\Contracts;

use App\Enums\ContractAmendmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractAmendmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amendment_type' => ['required', Rule::enum(ContractAmendmentType::class)],
            'description' => ['required', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'max:2000'],
            'new_value' => ['nullable', 'string', 'max:500'],
        ];
    }
}
