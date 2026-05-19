<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApproveStaffBulkMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
