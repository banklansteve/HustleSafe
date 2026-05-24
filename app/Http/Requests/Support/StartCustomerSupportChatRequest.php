<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCustomerSupportChatRequest extends FormRequest
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
        return [
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'category' => ['required', 'string', Rule::in(array_keys(config('customer_support.categories', [])))],
            'description' => ['nullable', 'string', 'max:2000'],
            'initial_message' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
