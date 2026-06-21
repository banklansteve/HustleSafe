<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;

class ContinueQuestContractRequest extends FormRequest
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
        $allowed = array_keys(config('recurring_engagement.contract_duration_options', []));

        return [
            'contract_duration_months' => ['required', 'integer', 'in:'.implode(',', $allowed)],
            'confirm' => ['accepted'],
        ];
    }
}
