<?php

namespace App\Http\Requests\Admin\PremiumPatrol;

use App\Enums\QuestBoostTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PremiumPatrolGrantBoostRequest extends FormRequest
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
            'tier' => ['required', Rule::enum(QuestBoostTier::class)],
            'grant_reason' => ['required', 'string', 'max:500'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
