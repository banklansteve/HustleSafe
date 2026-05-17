<?php

namespace App\Http\Requests\Disputes;

use App\Enums\QuestDisputeReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestDisputeRequest extends FormRequest
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
            'reason' => ['required', 'string', Rule::enum(QuestDisputeReason::class)],
            'opening_summary' => ['required', 'string', 'min:40', 'max:8000'],
            'structured_intake' => ['nullable', 'array'],
            'structured_intake.evidence_links' => ['nullable', 'array', 'max:10'],
            'structured_intake.evidence_links.*' => ['nullable', 'string', 'max:2048'],
            'structured_intake.deliverable_checklist' => ['nullable', 'array', 'max:20'],
            'structured_intake.deliverable_checklist.*' => ['nullable', 'string', 'max:500'],
            'structured_intake.contract_clause_reference' => ['nullable', 'string', 'max:2000'],
            'structured_intake.requested_outcome' => ['nullable', 'string', Rule::in(['full_refund', 'partial_refund', 'rework', 'release_payment', 'other'])],
            'structured_intake.silence_days_observed' => ['nullable', 'integer', 'min:0', 'max:365'],
            'confirm_philosophy' => ['accepted'],
        ];
    }
}
