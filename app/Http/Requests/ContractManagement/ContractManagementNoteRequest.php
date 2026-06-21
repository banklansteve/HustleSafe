<?php

namespace App\Http\Requests\ContractManagement;

use Illuminate\Foundation\Http\FormRequest;

class ContractManagementNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:8', 'max:2000'],
        ];
    }
}
