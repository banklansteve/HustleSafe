<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProposalClarificationQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');
        $user = $this->user();

        return $user && $quest && (int) $quest->client_id === (int) $user->id;
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:20', 'max:2000'],
            'prompt_key' => ['nullable', 'string', 'max:64'],
            'prompt_category' => ['nullable', 'string', 'max:32'],
        ];
    }
}
