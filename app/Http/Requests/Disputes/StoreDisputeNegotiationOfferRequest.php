<?php

namespace App\Http\Requests\Disputes;

use App\Enums\DisputeResolutionOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisputeNegotiationOfferRequest extends FormRequest
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
            'option' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (DisputeResolutionOption $o): string => $o->value,
                    DisputeResolutionOption::cases()
                )),
            ],
            'client_share_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'extend_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'revision_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:today'],
            'terms_note' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
