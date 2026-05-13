<?php

namespace App\Http\Requests\FreelancerCredential;

use App\Enums\CredentialType;
use App\Models\FreelancerCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateFreelancerCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cred = $this->route('freelancerCredential');

        return $cred instanceof FreelancerCredential
            && $this->user()?->id === $cred->user_id
            && $this->user()?->role?->slug === 'freelancer';
    }

    protected function prepareForValidation(): void
    {
        $cred = $this->route('freelancerCredential');
        if ($cred instanceof FreelancerCredential) {
            $this->merge(['credential_type' => $cred->credential_type]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credential_type' => ['required', new Enum(CredentialType::class)],
            'title' => ['required', 'string', 'max:255'],
            'issuing_authority' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'coverage_summary' => ['nullable', 'string', 'max:5000'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpeg,jpg,png,webp'],
        ];
    }
}
