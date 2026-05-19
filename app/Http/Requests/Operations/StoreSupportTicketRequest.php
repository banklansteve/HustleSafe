<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array((string) $this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'quest_conversation_thread_id' => ['nullable', 'integer', 'exists:quest_conversation_threads,id'],
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['required', 'string', 'max:8000'],
        ];
    }
}
