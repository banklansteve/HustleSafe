<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestDeliverySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $this->user() !== null
            && $quest !== null
            && (int) $quest->freelancer_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'min:20', 'max:5000'],
            'delivery_url' => ['nullable', 'url', 'max:2048'],
            'confirm' => ['accepted'],
        ];
    }
}
