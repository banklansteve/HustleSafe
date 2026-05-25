<?php

namespace App\Services\Verification;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\User;
use App\Models\UserIdentityDocument;
use App\Models\UserVerification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityDocumentUniquenessService
{
    /**
     * @var list<string>
     */
    public const GOVERNMENT_ID_TYPES = [
        'nin',
        'bvn',
        'passport',
        'national_id',
        'drivers_licence',
        'voters_card',
    ];

    public function assertAvailableForUser(User $user, string $documentKind, string $identifier): void
    {
        $kind = $this->normalizeKind($documentKind);
        $normalized = $this->normalizeValue($kind, $identifier);

        if ($normalized === '') {
            return;
        }

        $conflictId = $this->conflictingUserId($kind, $normalized, (int) $user->id);

        if ($conflictId !== null) {
            throw ValidationException::withMessages([
                'identifier_number' => __('This :document is already linked to another HustleSafe account. Each government ID can only be used once.', [
                    'document' => $this->labelForKind($kind),
                ]),
            ]);
        }
    }

    public function registerForUser(User $user, string $documentKind, string $identifier): void
    {
        $kind = $this->normalizeKind($documentKind);
        $normalized = $this->normalizeValue($kind, $identifier);

        if ($normalized === '') {
            return;
        }

        UserIdentityDocument::query()->updateOrCreate(
            [
                'document_kind' => $kind,
                'number_hash' => $this->hashValue($normalized),
            ],
            [
                'user_id' => $user->id,
                'normalized_last4' => Str::substr($normalized, -4),
            ],
        );
    }

    public function conflictingUserId(string $documentKind, string $identifier, ?int $exceptUserId = null): ?int
    {
        $kind = $this->normalizeKind($documentKind);
        $normalized = $this->normalizeValue($kind, $identifier);

        if ($normalized === '') {
            return null;
        }

        $hash = $this->hashValue($normalized);

        $registryConflict = UserIdentityDocument::query()
            ->where('document_kind', $kind)
            ->where('number_hash', $hash)
            ->when($exceptUserId !== null, fn ($q) => $q->where('user_id', '<>', $exceptUserId))
            ->value('user_id');

        if ($registryConflict !== null) {
            return (int) $registryConflict;
        }

        if ($kind === 'nin') {
            $columnConflict = User::query()
                ->whereNotNull('nin')
                ->where('nin', $normalized)
                ->when($exceptUserId !== null, fn ($q) => $q->where('id', '<>', $exceptUserId))
                ->value('id');

            if ($columnConflict !== null) {
                return (int) $columnConflict;
            }
        }

        if ($kind === 'bvn') {
            $columnConflict = User::query()
                ->whereNotNull('bvn')
                ->where('bvn', $normalized)
                ->when($exceptUserId !== null, fn ($q) => $q->where('id', '<>', $exceptUserId))
                ->value('id');

            if ($columnConflict !== null) {
                return (int) $columnConflict;
            }
        }

        return $this->conflictFromVerificationRecords($kind, $normalized, $exceptUserId);
    }

    public function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return match ($kind) {
            'national-id', 'national_id_card' => 'national_id',
            'drivers-license', 'driver_licence', 'drivers_license' => 'drivers_licence',
            UserVerificationCategory::Nin->value => 'nin',
            UserVerificationCategory::Bvn->value => 'bvn',
            default => $kind,
        };
    }

    public function normalizeValue(string $kind, string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (in_array($kind, ['nin', 'bvn'], true)) {
            return preg_replace('/\D+/', '', $value) ?? '';
        }

        return strtoupper(preg_replace('/[\s\-]+/', '', $value) ?? '');
    }

    public function hashValue(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    private function labelForKind(string $kind): string
    {
        return match ($kind) {
            'nin' => 'NIN',
            'bvn' => 'BVN',
            'passport' => 'passport number',
            'national_id' => 'national ID number',
            'drivers_licence' => 'driver\'s licence number',
            'voters_card' => 'voter\'s card number',
            default => 'identity document',
        };
    }

    private function conflictFromVerificationRecords(string $kind, string $normalized, ?int $exceptUserId): ?int
    {
        $verifications = UserVerification::query()
            ->when($exceptUserId !== null, fn ($q) => $q->where('user_id', '<>', $exceptUserId))
            ->whereIn('status', [
                UserVerificationStatus::Pending,
                UserVerificationStatus::InReview,
                UserVerificationStatus::Verified,
                UserVerificationStatus::Approved,
            ])
            ->where(function ($query) use ($kind): void {
                if ($kind === 'nin') {
                    $query->where('category', UserVerificationCategory::Nin);
                } elseif ($kind === 'bvn') {
                    $query->where('category', UserVerificationCategory::Bvn);
                } else {
                    $query->where('category', UserVerificationCategory::IdentityAddress);
                }
            })
            ->get(['id', 'user_id', 'category', 'metadata', 'encrypted_identifier']);

        foreach ($verifications as $verification) {
            $recordKind = $verification->category === UserVerificationCategory::IdentityAddress
                ? $this->normalizeKind((string) data_get($verification->metadata, 'id_type', ''))
                : $verification->category->value;

            if ($recordKind !== $kind) {
                continue;
            }

            $recordValue = $this->identifierFromVerification($verification);

            if ($recordValue !== '' && $this->normalizeValue($kind, $recordValue) === $normalized) {
                return (int) $verification->user_id;
            }
        }

        return null;
    }

    private function identifierFromVerification(UserVerification $verification): string
    {
        if ($verification->category === UserVerificationCategory::Bvn && filled($verification->encrypted_identifier)) {
            try {
                return Crypt::decryptString((string) $verification->encrypted_identifier);
            } catch (\Throwable) {
                return '';
            }
        }

        return (string) data_get($verification->metadata, 'identifier_number', '');
    }
}
