<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKycDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'approve_note', 'request_correction', 'reject', 'reject_investigate', 'reject_suspend', 'award_badge', 'revoke_badge'])],
            'reason_code' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:5000'],
            'correction_fields' => ['nullable', 'array'],
            'correction_fields.*' => ['string', 'max:80'],
            'portfolio_scores' => ['nullable', 'array'],
            'portfolio_scores.*.criterion' => ['required_with:portfolio_scores', 'string', 'max:120'],
            'portfolio_scores.*.score' => ['required_with:portfolio_scores', 'integer', 'min:1', 'max:5'],
            'portfolio_scores.*.feedback' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('action') === 'reject_suspend') {
                if ($this->user()?->role?->slug !== 'super_admin') {
                    $validator->errors()->add('action', 'Only a super admin can reject and suspend.');
                }

                if (mb_strlen((string) $this->input('note')) < 100) {
                    $validator->errors()->add('note', 'Reject and suspend requires a written reason of at least 100 characters.');
                }
            }
        });
    }
}
