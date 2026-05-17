<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminManagementRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminRecordRequest extends FormRequest
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
        $resourceKey = (string) $this->route('resource');
        $rules = [
            'audit_reason' => ['required', 'string', 'min:8', 'max:500'],
        ];

        foreach (AdminManagementRegistry::editFields($resourceKey) as $field) {
            $schema = AdminManagementRegistry::fieldSchema($resourceKey)[$field] ?? [];
            if (($schema['type'] ?? null) === 'key_value') {
                $rules[$field] = ['sometimes', 'nullable', 'array'];

                continue;
            }

            $fieldRules = isset($schema['rules'])
                ? explode('|', (string) $schema['rules'])
                : ['nullable', 'string', 'max:255'];

            $fieldRules = array_values(array_filter(
                $fieldRules,
                static fn (string $rule) => $rule !== 'required' && ! str_starts_with($rule, 'required:'),
            ));

            $rules[$field] = array_merge(['sometimes'], $fieldRules);
        }

        return $rules;
    }
}
