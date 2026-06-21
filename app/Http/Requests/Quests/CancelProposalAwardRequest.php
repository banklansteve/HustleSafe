<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;

class CancelProposalAwardRequest extends FormRequest
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
            'confirm' => ['accepted'],
            'reason' => ['nullable', 'string', 'min:8', 'max:1000'],
        ];
    }
}
