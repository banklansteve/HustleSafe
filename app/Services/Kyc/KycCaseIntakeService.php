<?php

namespace App\Services\Kyc;

use App\Enums\UserVerificationCategory;
use App\Models\KycReviewCase;
use App\Models\UserVerification;

class KycCaseIntakeService
{
    public function createFromVerification(UserVerification $verification, string $reason = 'manual_review'): KycReviewCase
    {
        $verification->loadMissing('user');
        $priority = $this->priorityFor($reason);
        $metadata = $verification->metadata ?? [];
        $provider = $verification->provider_response ?? [];

        $case = KycReviewCase::query()->create([
            'user_id' => $verification->user_id,
            'user_verification_id' => $verification->id,
            'target_tier' => $verification->target_tier ?: $this->targetTierFor($verification),
            'verification_type' => $verification->category?->value ?? (string) $verification->category,
            'status' => 'pending',
            'priority' => $priority,
            'queue_reason' => $verification->queue_reason ?: $reason,
            'confidence_score' => $verification->confidence_score,
            'submitted_snapshot' => [
                'name' => $verification->user?->name,
                'date_of_birth' => $verification->user?->date_of_birth?->toDateString(),
                'phone' => $verification->user?->phone,
                'identifier_number' => $metadata['identifier_number'] ?? null,
                'id_type' => $metadata['id_type'] ?? null,
                'business_name' => $verification->user?->company_name,
            ],
            'provider_snapshot' => $provider,
            'comparison' => $this->comparison($verification),
            'entered_queue_at' => now(),
        ]);

        foreach ($metadata['documents'] ?? [] as $doc) {
            if (! isset($doc['path'])) {
                continue;
            }

            $case->documents()->create([
                'label' => $doc['label'] ?? 'Document',
                'document_type' => $metadata['id_type'] ?? 'supporting_document',
                'disk' => 'local',
                'path' => $doc['path'],
                'original_name' => $doc['original_name'] ?? null,
            ]);
        }

        foreach ($verification->document_paths ?? [] as $path) {
            if ($case->documents()->where('path', $path)->exists()) {
                continue;
            }

            $case->documents()->create([
                'label' => $verification->category === UserVerificationCategory::LivePresence ? 'Selfie / liveness image' : 'Document',
                'document_type' => $verification->category?->value ?? 'supporting_document',
                'disk' => 'local',
                'path' => $path,
            ]);
        }

        return $case;
    }

    private function targetTierFor(UserVerification $verification): int
    {
        return match ($verification->category) {
            UserVerificationCategory::Identity => 2,
            UserVerificationCategory::Address => 3,
            UserVerificationCategory::LivePresence => 4,
            UserVerificationCategory::Qualification => 4,
            UserVerificationCategory::Business => 5,
            default => 2,
        };
    }

    private function priorityFor(string $reason): string
    {
        return match ($reason) {
            'duplicate_identity' => 'critical',
            'mismatch' => 'high',
            'low_confidence', 'liveness_failed', 'manual_escalation' => 'medium',
            default => 'standard',
        };
    }

    private function comparison(UserVerification $verification): array
    {
        $user = $verification->user;
        $provider = $verification->provider_response ?? [];

        return [
            'name' => $this->compare((string) $user?->name, (string) data_get($provider, 'name')),
            'date_of_birth' => $this->compare((string) optional($user?->date_of_birth)->toDateString(), (string) data_get($provider, 'date_of_birth')),
            'phone' => $this->compare((string) $user?->phone, (string) data_get($provider, 'phone')),
        ];
    }

    private function compare(string $submitted, string $provider): string
    {
        if ($provider === '') {
            return 'unknown';
        }

        if (mb_strtolower(trim($submitted)) === mb_strtolower(trim($provider))) {
            return 'match';
        }

        similar_text(mb_strtolower($submitted), mb_strtolower($provider), $percent);

        return $percent >= 70 ? 'partial' : 'mismatch';
    }
}
