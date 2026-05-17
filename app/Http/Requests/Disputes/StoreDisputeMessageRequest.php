<?php

namespace App\Http\Requests\Disputes;

use App\Enums\DisputeMessageKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisputeMessageRequest extends FormRequest
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
            'kind' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (DisputeMessageKind $k): string => $k->value,
                    array_values(array_filter(
                        DisputeMessageKind::cases(),
                        static fn (DisputeMessageKind $k): bool => $k !== DisputeMessageKind::System
                    ))
                )),
            ],
            'body' => ['required', 'string', 'min:2', 'max:8000'],
            'structured_key' => ['nullable', 'string', 'max:96'],
            'structured_payload' => ['nullable', 'array'],
            'structured_payload.steps_completed' => ['nullable', 'array', 'max:30'],
            'structured_payload.steps_completed.*' => ['nullable', 'string', 'max:500'],
            'structured_payload.timeline' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
