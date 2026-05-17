<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationThreadVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['hide', 'delete', 'restore'])],
            'reason' => ['required_unless:action,restore', 'nullable', 'string', 'min:10', 'max:2000'],
        ];
    }
}
