<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsGatewayService
{
    public function send(User $user, string $message): bool
    {
        $phone = $this->normalizePhone($user->phone ?? $user->mobile ?? null);
        if ($phone === null) {
            return false;
        }

        $apiKey = (string) config('services.termii.api_key', '');
        if ($apiKey === '') {
            Log::info('sms.skipped', ['user_id' => $user->id, 'message' => $message]);

            return false;
        }

        try {
            $response = Http::withToken($apiKey)
                ->post('https://api.ng.termii.com/api/sms/send', [
                    'to' => $phone,
                    'from' => substr((string) config('services.termii.sender_id', 'HustleSafe'), 0, 11),
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => 'generic',
                    'api_key' => $apiKey,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('sms.failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '234')) {
            return '234'.$digits;
        }

        return $digits;
    }
}
