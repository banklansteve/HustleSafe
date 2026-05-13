<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class AccountVisibilityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'settings' => ['required', 'array'],
        ];
        foreach ($this->allowedSettingKeys() as $key) {
            $rules['settings.'.$key] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    protected function allowedSettingKeys(): array
    {
        $slug = $this->user()->role?->slug;

        if ($slug === 'freelancer') {
            return array_keys(config('profile.public_defaults', []));
        }

        return array_keys(config('profile.client_public_defaults', []));
    }

    /**
     * @return array<string, bool>
     */
    public function validatedSettings(): array
    {
        $allowed = $this->allowedSettingKeys();
        $input = $this->input('settings', []);

        $out = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = (bool) $input[$key];
            }
        }

        return $out;
    }
}
