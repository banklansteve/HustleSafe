<?php

namespace App\Http\Requests\Admin;

use App\Models\FeaturedQuestListing;
use App\Models\Quest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeaturedQuestListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'quest_id' => ['required', 'integer', 'exists:quests,id'],
            'tier' => ['required', Rule::in(['standard', 'premium', 'elite'])],
            'duration_days' => ['required', 'integer', Rule::in([3, 7, 14, 30])],
            'amount_paid_minor' => ['nullable', 'integer', 'min:0'],
            'manual_grant_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $quest = Quest::query()->select(['id', 'client_id'])->find($this->integer('quest_id'));
            if ($quest === null) {
                return;
            }

            if ($quest->client_id === null) {
                $validator->errors()->add('quest_id', 'This quest cannot be featured because it is not linked to a client account.');
                return;
            }

            $alreadyFeatured = FeaturedQuestListing::query()
                ->where('quest_id', $quest->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->exists();

            if ($alreadyFeatured) {
                $validator->errors()->add('quest_id', 'This quest already has an active featured listing.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'quest_id.exists' => 'No quest was found with that ID.',
            'duration_days.in' => 'Choose a supported duration: 3, 7, 14, or 30 days.',
            'manual_grant_reason.min' => 'Please provide at least 10 characters explaining why this feature was granted.',
        ];
    }
}
