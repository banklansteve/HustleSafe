<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array((string) $this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'waiting_on_customer', 'waiting_on_internal', 'in_review', 'resolved', 'closed'])],
            'reason' => ['required', 'string', 'max:4000'],
        ];
    }
}
