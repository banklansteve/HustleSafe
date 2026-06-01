<?php

namespace App\Http\Requests\Admin;

use App\Enums\QuestBoostTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantQuestBoostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quest_id' => ['required', 'integer', 'exists:quests,id'],
            'tier' => ['required', Rule::in(array_map(fn (QuestBoostTier $t) => $t->value, QuestBoostTier::ordered()))],
            'grant_reason' => ['required', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
