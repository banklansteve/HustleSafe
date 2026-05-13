<?php

namespace App\Http\Requests\Quests;

use App\Models\QuestOffer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQuestOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'freelancer';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'pitch' => ['required', 'string', 'max:8000'],
            'quoted_amount_minor' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $user = $this->user();
            $quest = $this->route('quest');
            if ($user === null || $quest === null) {
                return;
            }

            $exists = QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->where('freelancer_id', $user->id)
                ->exists();

            if ($exists) {
                $v->errors()->add('offer', __('You have already sent an offer for this quest.'));
            }
        });
    }
}
