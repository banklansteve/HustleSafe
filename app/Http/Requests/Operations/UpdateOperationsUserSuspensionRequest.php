<?php

namespace App\Http\Requests\Operations;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationsUserSuspensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role?->slug !== 'admin') {
            return false;
        }

        $target = $this->route('user');
        if (! $target instanceof User) {
            return false;
        }

        if ($target->id === $this->user()->id) {
            return false;
        }

        if (in_array($target->role?->slug, ['super_admin', 'admin'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'suspended' => ['required', 'boolean'],
        ];
    }
}
