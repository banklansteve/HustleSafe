<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffResponseTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $situations = array_keys(config('operations.proactive_outreach.situations', []));
        $templateId = $this->route('template')?->id;

        return [
            'slug' => ['sometimes', 'string', 'max:80', Rule::unique('staff_response_templates', 'slug')->ignore($templateId)],
            'situation_key' => ['sometimes', 'string', Rule::in($situations)],
            'category' => ['sometimes', 'string', 'max:48'],
            'title' => ['sometimes', 'string', 'max:200'],
            'subject' => ['sometimes', 'string', 'max:200'],
            'body' => ['sometimes', 'string', 'min:20', 'max:8000'],
            'policy_tags' => ['nullable', 'array'],
            'policy_tags.*' => ['string', 'max:48'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
