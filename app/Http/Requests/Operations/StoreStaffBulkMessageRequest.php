<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffBulkMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->role?->slug === 'admin';
    }

    public function rules(): array
    {
        return [
            'audience' => ['required', 'string', Rule::in(['all_users', 'clients', 'freelancers', 'verified_users'])],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(['mail', 'in_app'])],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:8000'],
        ];
    }
}
