<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
