<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;

class RequestDeliveryRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $this->user() !== null
            && $quest !== null
            && (int) $quest->client_id === (int) $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:20', 'max:2000'],
            'confirm' => ['accepted'],
        ];
    }
}
