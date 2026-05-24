<?php

namespace App\Services\Verification;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\UserVerification;
use App\Support\FormatsHumanDateTime;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UserVerificationPresentationService
{
    /**
     * @return array<string, mixed>
     */
    public function forReview(UserVerification $verification, string $documentRouteName = 'operations.api.verifications.document'): array
    {
        $verification->loadMissing(['user:id,name,email,avatar_url,current_verification_level,verification_tier,created_at', 'reviewer:id,name,email']);

        $meta = is_array($verification->metadata) ? $verification->metadata : [];
        $category = $verification->category;

        return [
            'id' => $verification->id,
            'category' => $category?->value ?? (string) $verification->category,
            'category_label' => $this->categoryLabel($category),
            'verification_type' => $verification->verification_type ?: ($category?->value ?? ''),
            'verification_type_label' => $this->verificationTypeLabel($verification),
            'status' => $verification->status?->value ?? (string) $verification->status,
            'status_label' => $this->statusLabel($verification->status),
            'target_tier' => $verification->target_tier,
            'submitted_at' => $verification->submitted_at?->timezone(config('app.timezone'))->toIso8601String(),
            'submitted_at_label' => FormatsHumanDateTime::format($verification->submitted_at, config('app.timezone')),
            'reviewed_at' => $verification->reviewed_at?->timezone(config('app.timezone'))->toIso8601String(),
            'reviewed_at_label' => FormatsHumanDateTime::format($verification->reviewed_at, config('app.timezone')),
            'rejection_reason' => $verification->rejection_reason,
            'queue_reason' => $verification->queue_reason,
            'queue_reason_label' => $this->queueReasonLabel($verification->queue_reason),
            'is_escalated' => $this->isEscalated($verification),
            'escalation' => $this->escalationPayload($verification),
            'user' => $verification->user ? [
                'id' => $verification->user->id,
                'name' => $verification->user->name,
                'email' => $verification->user->email,
                'avatar_url' => $verification->user->avatar_url,
                'level' => $verification->user->current_verification_level ?? $verification->user->verification_tier ?? 0,
            ] : null,
            'reviewer' => $verification->reviewer ? [
                'name' => $verification->reviewer->name,
                'email' => $verification->reviewer->email,
            ] : null,
            'fields' => $this->identityFields($verification, $meta),
            'documents' => $this->documents($verification, $meta, $documentRouteName),
            'provider_summary' => $this->providerSummary($verification->provider_response ?? []),
            'provider_reference' => $verification->provider_reference,
            'confidence_score' => $verification->confidence_score,
        ];
    }

    public function isEscalated(UserVerification $verification): bool
    {
        $status = $verification->status?->value ?? (string) $verification->status;

        return $status === UserVerificationStatus::Flagged->value
            || filled($verification->admin_concern);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{key: string, label: string, value: string}>
     */
    private function identityFields(UserVerification $verification, array $meta): array
    {
        $fields = [];
        $category = $verification->category;

        if (! empty($meta['id_type'])) {
            $fields[] = [
                'key' => 'id_type',
                'label' => __('ID type'),
                'value' => $this->idTypeLabel((string) $meta['id_type']),
            ];
        }

        if (! empty($meta['address_document_type'])) {
            $fields[] = [
                'key' => 'address_document_type',
                'label' => __('Address document'),
                'value' => $this->addressDocumentLabel((string) $meta['address_document_type']),
            ];
        }

        $identifier = $this->resolveIdentifier($verification, $meta, $category);
        if ($identifier !== null) {
            $fields[] = [
                'key' => 'identifier',
                'label' => $this->identifierLabel($category, $meta),
                'value' => $identifier,
            ];
        }

        if (! empty($meta['registered_business_name'])) {
            $fields[] = [
                'key' => 'registered_business_name',
                'label' => __('Registered business name'),
                'value' => (string) $meta['registered_business_name'],
            ];
        }

        if (! empty($meta['cac_number'])) {
            $fields[] = [
                'key' => 'cac_number',
                'label' => __('CAC number'),
                'value' => (string) $meta['cac_number'],
            ];
        }

        if (! empty($meta['confirmed_address'])) {
            $fields[] = [
                'key' => 'confirmed_address',
                'label' => __('Confirmed address'),
                'value' => (string) $meta['confirmed_address'],
            ];
        }

        foreach ($meta['entries'] ?? [] as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $summary = collect([
                $entry['what_submitting'] ?? null,
                $entry['awarding_body'] ?? null,
                isset($entry['year']) ? (string) $entry['year'] : null,
            ])->filter()->implode(' · ');
            if ($summary === '') {
                continue;
            }
            $fields[] = [
                'key' => 'professional_'.$index,
                'label' => __('Credential :n', ['n' => $index + 1]),
                'value' => $summary,
            ];
        }

        if (filled($verification->decision_reason_code)) {
            $label = app(VerificationDecisionReasonService::class)->label($verification->decision_reason_code);
            if ($label) {
                $fields[] = [
                    'key' => 'decision_reason',
                    'label' => __('Reviewer reason'),
                    'value' => $label,
                ];
            }
        }

        if (filled($verification->decision_reason_note)) {
            $fields[] = [
                'key' => 'decision_reason_note',
                'label' => __('Reviewer note'),
                'value' => (string) $verification->decision_reason_note,
            ];
        }

        if (! empty($meta['note'])) {
            $fields[] = [
                'key' => 'note',
                'label' => __('Submission note'),
                'value' => (string) $meta['note'],
            ];
        }

        if (! empty($meta['kind']) && $category === UserVerificationCategory::LivePresence) {
            $fields[] = [
                'key' => 'kind',
                'label' => __('Capture type'),
                'value' => __('Selfie holding approved ID'),
            ];
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    private function documents(UserVerification $verification, array $meta, string $routeName): array
    {
        $items = [];
        $seen = [];

        foreach ($meta['documents'] ?? [] as $index => $doc) {
            if (! is_array($doc)) {
                continue;
            }
            $path = (string) ($doc['path'] ?? '');
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $items[] = $this->documentRow(
                $verification,
                $path,
                (string) ($doc['label'] ?? $doc['original_name'] ?? __('Document :n', ['n' => $index + 1])),
                (string) ($doc['original_name'] ?? ''),
                $routeName,
            );
        }

        foreach ($verification->document_paths ?? [] as $index => $path) {
            $path = (string) $path;
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $items[] = $this->documentRow(
                $verification,
                $path,
                __('Uploaded file :n', ['n' => $index + 1]),
                basename($path),
                $routeName,
            );
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentRow(UserVerification $verification, string $path, string $label, string $originalName, string $routeName): array
    {
        $mime = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';

        return [
            'label' => $label,
            'original_name' => $originalName !== '' ? $originalName : basename($path),
            'path' => $path,
            'mime_type' => $mime,
            'is_image' => str_starts_with($mime, 'image/'),
            'is_pdf' => $mime === 'application/pdf',
            'url' => route($routeName, ['verification' => $verification->id, 'path' => $path]),
        ];
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return list<array{label: string, value: string}>
     */
    private function providerSummary(array $provider): array
    {
        if ($provider === []) {
            return [];
        }

        $rows = [];
        foreach (['status', 'match_status', 'first_name', 'last_name', 'name', 'date_of_birth', 'phone', 'message'] as $key) {
            $value = data_get($provider, $key);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $rows[] = [
                'label' => Str::headline(str_replace('_', ' ', $key)),
                'value' => (string) $value,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function escalationPayload(UserVerification $verification): ?array
    {
        if (! $this->isEscalated($verification)) {
            return null;
        }

        return [
            'label' => __('Escalated to Super Admin'),
            'reason' => $verification->admin_concern,
            'referred_at' => $verification->referred_at?->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }

    private function resolveIdentifier(UserVerification $verification, array $meta, ?UserVerificationCategory $category): ?string
    {
        if ($category === UserVerificationCategory::Bvn && filled($verification->encrypted_identifier)) {
            try {
                return Crypt::decryptString((string) $verification->encrypted_identifier);
            } catch (\Throwable) {
                return $meta['identifier_masked'] ?? null;
            }
        }

        if (! empty($meta['identifier_number'])) {
            return (string) $meta['identifier_number'];
        }

        return $meta['identifier_masked'] ?? null;
    }

    private function identifierLabel(?UserVerificationCategory $category, array $meta): string
    {
        if ($category === UserVerificationCategory::Nin) {
            return __('NIN');
        }
        if ($category === UserVerificationCategory::Bvn) {
            return __('BVN');
        }
        if ($category === UserVerificationCategory::Tin) {
            return __('TIN');
        }
        if (! empty($meta['cac_number'])) {
            return __('Registration number');
        }

        return __('ID / reference number');
    }

    private function categoryLabel(?UserVerificationCategory $category): string
    {
        return match ($category) {
            UserVerificationCategory::Email => __('Email verification'),
            UserVerificationCategory::Identity => __('Government ID'),
            UserVerificationCategory::Address => __('Proof of address'),
            UserVerificationCategory::IdentityAddress => __('Identity & address'),
            UserVerificationCategory::Nin => __('NIN'),
            UserVerificationCategory::Bvn => __('BVN'),
            UserVerificationCategory::Cac => __('CAC'),
            UserVerificationCategory::Tin => __('TIN'),
            UserVerificationCategory::ProfessionalCertificate => __('Professional certificate'),
            UserVerificationCategory::PortfolioReview => __('Portfolio review'),
            UserVerificationCategory::Qualification => __('Qualification'),
            UserVerificationCategory::Business => __('Business verification'),
            UserVerificationCategory::LivePresence => __('Selfie with ID'),
            default => __('Verification'),
        };
    }

    private function verificationTypeLabel(UserVerification $verification): string
    {
        $type = (string) ($verification->verification_type ?: '');

        return $type !== '' ? Str::headline(str_replace('_', ' ', $type)) : $this->categoryLabel($verification->category);
    }

    private function statusLabel(?UserVerificationStatus $status): string
    {
        return match ($status) {
            UserVerificationStatus::Pending => __('Pending review'),
            UserVerificationStatus::InReview => __('In review'),
            UserVerificationStatus::Flagged => __('Escalated'),
            UserVerificationStatus::Verified, UserVerificationStatus::Approved => __('Approved'),
            UserVerificationStatus::Unverified => __('Corrections requested'),
            UserVerificationStatus::Rejected => __('Rejected'),
            default => Str::headline((string) ($status?->value ?? 'unknown')),
        };
    }

    private function queueReasonLabel(?string $reason): ?string
    {
        return match ($reason) {
            'duplicate_identity' => __('Possible duplicate identity'),
            'cac_review_required' => __('Business registration review'),
            'liveness_review' => __('Liveness / selfie review'),
            'manual_escalation' => __('Manual review required'),
            'manual_review' => __('Standard manual review'),
            default => $reason ? Str::headline(str_replace('_', ' ', $reason)) : null,
        };
    }

    private function idTypeLabel(string $idType): string
    {
        return match ($idType) {
            'nin' => __('National ID (NIN slip/card)'),
            'bvn' => __('Bank Verification Number'),
            'passport' => __('International passport'),
            'national_id' => __('National ID card'),
            'drivers_licence' => __('Driver\'s licence'),
            'voters_card' => __('Voter\'s card'),
            default => Str::headline(str_replace('_', ' ', $idType)),
        };
    }

    private function addressDocumentLabel(string $type): string
    {
        return match ($type) {
            'utility_bill' => __('Utility bill'),
            'tenancy_agreement' => __('Tenancy agreement'),
            'bank_statement' => __('Bank statement'),
            default => Str::headline(str_replace('_', ' ', $type)),
        };
    }
}
