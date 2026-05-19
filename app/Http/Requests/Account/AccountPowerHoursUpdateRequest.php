<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountPowerHoursUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'freelancer';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'timezone' => ['required', 'timezone'],
            'response_mode' => ['required', Rule::in(['same_day', 'next_business_day', 'flexible'])],
            'note' => ['nullable', 'string', 'max:240'],
            'weekly' => ['required', 'array'],
            'weekly.*.enabled' => ['required', 'boolean'],
            'weekly.*.start' => ['required', 'date_format:H:i'],
            'weekly.*.end' => ['required', 'date_format:H:i'],
        ];
    }
}
