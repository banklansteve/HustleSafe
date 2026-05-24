<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackClient
{
    public function enabled(): bool
    {
        return (bool) config('payment.paystack.enabled')
            && filled(config('payment.paystack.secret_key'));
    }

    public function publicKey(): ?string
    {
        return config('payment.paystack.public_key');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initializeTransaction(array $payload): array
    {
        return $this->request('post', '/transaction/initialize', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyTransaction(string $reference): array
    {
        return $this->request('get', '/transaction/verify/'.rawurlencode($reference));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBanks(string $country = 'nigeria'): array
    {
        $response = $this->request('get', '/bank', ['country' => $country, 'perPage' => 100]);

        return $response['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): array
    {
        return $this->request('get', '/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTransferRecipient(array $payload): array
    {
        return $this->request('post', '/transferrecipient', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initiateTransfer(array $payload): array
    {
        return $this->request('post', '/transfer', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTransferBulk(array $payload): array
    {
        return $this->request('post', '/transfer/bulk', $payload);
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('payment.paystack.webhook_secret');
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals($secret, hash_hmac('sha512', $rawBody, $secret));
    }

    /**
     * @param  array<string, mixed>|null  $query
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $query = null): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException(__('Paystack is not configured. Add sandbox keys to your environment.'));
        }

        $client = $this->http();
        $response = match (strtolower($method)) {
            'get' => $client->get($path, $query ?? []),
            'post' => $client->post($path, $query ?? []),
            default => throw new RuntimeException("Unsupported HTTP method [{$method}]"),
        };

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException(__('Unexpected Paystack response.'));
        }

        if (! ($json['status'] ?? false)) {
            $message = (string) ($json['message'] ?? __('Paystack request failed.'));

            throw new RuntimeException($message);
        }

        return $json;
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('payment.paystack.base_url'), '/'))
            ->withToken((string) config('payment.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }
}
