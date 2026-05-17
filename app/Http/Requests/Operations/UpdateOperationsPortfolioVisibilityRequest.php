<?php

namespace App\Http\Requests\Operations;

use App\Models\Portfolio;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationsPortfolioVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->role?->slug !== 'admin') {
            return false;
        }

        return $this->route('portfolio') instanceof Portfolio;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_hidden' => ['required', 'boolean'],
        ];
    }
}
