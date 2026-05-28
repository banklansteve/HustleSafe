<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class ConversationMonitoringActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
