<?php

namespace App\Services;

use App\Models\FreelancerBusinessProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Corporate Affairs Commission (CAC) registration checks.
 * Wire a verified API provider when available; until then this stays honest & non-destructive.
 */
class CacVerificationService
{
    /**
     * @return array{status: string, checked_at: Carbon|null, message: string|null}
     */
    public function refreshStatus(FreelancerBusinessProfile $profile): array
    {
        $rc = trim((string) ($profile->cac_registration_number ?? ''));
        if ($rc === '') {
            return [
                'status' => 'not_submitted',
                'checked_at' => null,
                'message' => null,
            ];
        }

        $recalcForUser = function () use ($profile): void {
            $profile->loadMissing('user');
            if ($profile->user !== null) {
                app(TrustScoreOrchestrator::class)->recalculate($profile->user->fresh());
            }
        };

        $endpoint = config('services.cac.verify_url');
        $token = config('services.cac.token');

        if (empty($endpoint) || empty($token)) {
            $profile->forceFill([
                'cac_verification_status' => 'pending',
                'cac_last_checked_at' => now(),
                'cac_verification_notes' => 'Automated CAC API not configured — manual review queue.',
            ])->save();

            $recalcForUser();

            return [
                'status' => 'pending',
                'checked_at' => now(),
                'message' => 'CAC integration pending configuration.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->post($endpoint, ['rc_number' => $rc]);

            $ok = $response->successful() && (bool) data_get($response->json(), 'verified', false);

            $profile->forceFill([
                'cac_verification_status' => $ok ? 'verified' : 'rejected',
                'cac_verified_at' => $ok ? now() : null,
                'cac_last_checked_at' => now(),
                'cac_verification_notes' => $response->json('message'),
            ])->save();

            $recalcForUser();

            return [
                'status' => $profile->cac_verification_status,
                'checked_at' => $profile->cac_last_checked_at,
                'message' => $profile->cac_verification_notes,
            ];
        } catch (\Throwable $e) {
            $profile->forceFill([
                'cac_verification_status' => 'pending',
                'cac_last_checked_at' => now(),
                'cac_verification_notes' => 'CAC check failed: '.$e->getMessage(),
            ])->save();

            $recalcForUser();

            return [
                'status' => 'pending',
                'checked_at' => now(),
                'message' => $profile->cac_verification_notes,
            ];
        }
    }
}
