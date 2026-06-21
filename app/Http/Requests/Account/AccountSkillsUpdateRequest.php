<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class AccountSkillsUpdateRequest extends FormRequest
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
            'skills' => ['present', 'array', 'max:30'],
            'skills.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'skills.max' => __('You can list up to 30 skills.'),
            'skills.*.max' => __('Each skill must be 50 characters or fewer.'),
        ];
    }
}
