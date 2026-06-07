<?php

namespace App\Http\Requests\Quests;

use App\Enums\QuestBoostTier;
use App\Models\Quest;
use App\Services\Quest\ClientQuestBoostService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestBoostCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $quest instanceof Quest
            && $this->user() !== null
            && (int) $this->user()->id === (int) $quest->client_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tier' => ['required', 'string', Rule::in(array_map(fn (QuestBoostTier $t) => $t->value, QuestBoostTier::ordered()))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $quest = $this->route('quest');
            if (! $quest instanceof Quest || ! $this->user()) {
                return;
            }

            $clientBoosts = app(ClientQuestBoostService::class);

            if (! $clientBoosts->canPurchase($quest, $this->user())) {
                $validator->errors()->add('tier', __('This quest cannot be boosted right now.'));

                return;
            }

            try {
                $clientBoosts->assertTierAllowed($quest, QuestBoostTier::from((string) $this->input('tier')));
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }
}
