<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentReviewFlagRequest extends FormRequest
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
            'anomaly_fingerprint' => ['required', 'string', 'max:120'],
            'anomaly_type' => ['required', 'string', 'max:60'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high'])],
            'payment_escrow_id' => ['nullable', 'integer', 'exists:payment_escrows,id'],
            'quest_id' => ['nullable', 'integer', 'exists:quests,id'],
            'wallet_transaction_id' => ['nullable', 'integer', 'exists:wallet_transactions,id'],
            'transaction_reference' => ['nullable', 'string', 'max:64'],
            'concern_note' => ['required', 'string', 'min:8', 'max:500'],
            'signal_payload' => ['nullable', 'array'],
        ];
    }
}
