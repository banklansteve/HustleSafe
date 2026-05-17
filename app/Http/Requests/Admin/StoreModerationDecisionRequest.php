<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModerationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'disable', 'approve_warning', 'edit_approve', 'remove', 'remove_warn', 'remove_suspend', 'request_revision', 'fraud_investigation', 'reinstate', 'uphold'])],
            'reason_code' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:5000'],
            'edited' => ['nullable', 'array'],
            'edited.title' => ['nullable', 'string', 'max:255'],
            'edited.description' => ['nullable', 'string', 'max:12000'],
            'edited.comment' => ['nullable', 'string', 'max:5000'],
            'edited.bio' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
