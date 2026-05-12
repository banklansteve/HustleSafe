<?php

namespace App\Http\Requests\Verification;

use App\Enums\UserVerificationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(UserVerificationCategory::class)],
            'freelancer_credential_id' => [
                Rule::requiredIf(fn () => $this->input('category') === UserVerificationCategory::Qualification->value),
                'nullable',
                'integer',
                Rule::exists('freelancer_credentials', 'id')->where('user_id', $this->user()->id),
            ],
            'document_paths' => ['nullable', 'array', 'max:8'],
            'document_paths.*' => ['string', 'max:512'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $cat = $this->input('category');
            if ($cat !== UserVerificationCategory::Qualification->value && $this->filled('freelancer_credential_id')) {
                $validator->errors()->add('freelancer_credential_id', __('Credential link is only used for qualification reviews.'));
            }
        });
    }
}
