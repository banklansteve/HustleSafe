<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffResponseTemplateRequest extends FormRequest
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

        return [
            'slug' => ['nullable', 'string', 'max:80', Rule::unique('staff_response_templates', 'slug')],
            'situation_key' => ['required', 'string', Rule::in($situations)],
            'category' => ['required', 'string', 'max:48'],
            'title' => ['required', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:20', 'max:8000'],
            'policy_tags' => ['nullable', 'array'],
            'policy_tags.*' => ['string', 'max:48'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
