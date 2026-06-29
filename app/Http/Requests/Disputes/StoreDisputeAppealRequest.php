<?php

namespace App\Http\Requests\Disputes;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisputeAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unfair_reason' => ['required', 'string', 'min:20', 'max:5000'],
            'proposed_option' => ['nullable', 'string', 'max:64'],
            'client_share_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'extend_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'revision_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'target_completion_date' => ['nullable', 'date'],
            'terms_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
