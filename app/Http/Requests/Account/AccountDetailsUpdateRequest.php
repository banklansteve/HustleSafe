<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountDetailsUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['state_id', 'local_government_id'] as $key) {
            if (! $this->has($key)) {
                continue;
            }
            $v = $this->input($key);
            if ($v === '' || $v === null || $v === 0 || $v === '0') {
                $merge[$key] = null;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:20000'],
            'phone' => ['nullable', 'string', 'max:48'],
            'profession' => ['nullable', 'string', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'hourly_rate_min' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate_max' => ['nullable', 'numeric', 'min:0'],
            'city' => ['nullable', 'string', 'max:120'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'local_government_id' => [
                'nullable',
                'integer',
                Rule::exists('local_governments', 'id')->where('state_id', (int) $this->input('state_id', 0)),
            ],
        ];
    }
}
