<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\Admin\AdminManagementRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAdminRecordRequest extends FormRequest
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
        $rules = [
            'audit_reason' => ['required', 'string', 'min:8', 'max:500'],
        ];

        foreach (AdminManagementRegistry::createFields($this->route('resource')) as $field) {
            $schema = AdminManagementRegistry::fieldSchema($this->route('resource'))[$field] ?? [];
            if (($schema['type'] ?? null) === 'key_value') {
                $rules[$field] = ['nullable', 'array'];

                continue;
            }

            $rules[$field] = isset($schema['rules'])
                ? explode('|', (string) $schema['rules'])
                : ['nullable'];
        }

        if ($this->route('resource') === 'users') {
            $rules['account_type'][] = 'in:client,freelancer,admin';
            $rules['current_password'] = ['nullable', 'current_password'];
            $rules['admin_creation_confirmation'] = ['nullable', 'accepted'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('resource') !== 'users') {
                return;
            }

            $roleSlug = null;
            if ($this->filled('role_id')) {
                $roleSlug = Role::query()->whereKey($this->integer('role_id'))->value('slug');
            }

            $adminTarget = in_array($roleSlug, ['admin', 'super_admin'], true) || $this->input('account_type') === 'admin';
            if (! $adminTarget) {
                return;
            }

            if ($this->user()?->role?->slug !== 'super_admin') {
                $validator->errors()->add('role_id', 'Only a super administrator can create admin accounts.');
            }

            if (! $this->filled('current_password')) {
                $validator->errors()->add('current_password', 'Confirm your current password before creating an admin account.');
            }

            if (! $this->boolean('admin_creation_confirmation')) {
                $validator->errors()->add('admin_creation_confirmation', 'Acknowledge the admin account security warning before continuing.');
            }

            if ($roleSlug === 'super_admin' && ! str_contains(strtolower((string) $this->input('audit_reason', '')), 'super admin')) {
                $validator->errors()->add('audit_reason', 'The audit reason must explicitly mention why a super admin account is required.');
            }
        });
    }
}
