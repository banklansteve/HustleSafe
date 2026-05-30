<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class ProactiveOutreachContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:8000'],
            'channel' => ['nullable', 'in:both,email,in_app'],
            'template_id' => ['nullable', 'integer', 'exists:staff_response_templates,id'],
            'template_slug' => ['nullable', 'string', 'max:80'],
        ];
    }
}
