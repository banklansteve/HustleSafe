<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrustWatchlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->slug, ['admin', 'super_admin'], true);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:300'],
            'review_by_date' => ['required', 'date', 'after_or_equal:today'],
            'severity' => ['required', 'in:observe,concern,urgent'],
            'visibility' => ['required', 'in:personal,team'],
            'label' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
