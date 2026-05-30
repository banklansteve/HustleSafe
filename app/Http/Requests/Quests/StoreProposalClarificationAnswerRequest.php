<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProposalClarificationAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');
        $user = $this->user();

        return $user && $offer && (int) $offer->freelancer_id === (int) $user->id;
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:15', 'max:2000'],
            'reply_to_message_id' => ['required', 'integer'],
        ];
    }
}
