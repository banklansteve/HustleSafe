<?php

namespace App\Http\Requests\Support;

use App\Services\Support\SupportTicketManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedSupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array((string) $this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(SupportTicketManagementService::STATUSES)],
            'summary' => ['required', 'string', 'max:4000'],
        ];
    }
}
