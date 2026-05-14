<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in([
                'spam',
                'harassment',
                'fraud',
                'off_platform_contact',
                'misleading',
                'copyright',
                'duplicate_listing',
                'unsafe_scope',
                'payment_dispute',
                'other',
            ])],
            'details' => ['nullable', 'string', 'max:4000'],
            'severity' => ['nullable', 'string', Rule::in(['low', 'standard', 'high', 'urgent'])],
            'evidence_url' => ['nullable', 'string', 'max:512', 'url'],
        ];
    }
}
