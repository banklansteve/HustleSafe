<?php

namespace App\Http\Requests\Quests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmProposalAwardRequest extends FormRequest
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
            'confirm' => ['accepted'],
            'confirm_scope' => ['accepted'],
            'confirm_price' => ['accepted'],
            'confirm_deadline' => ['accepted'],
        ];
    }
}
