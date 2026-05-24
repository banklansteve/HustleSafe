<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\EscrowPaymentService;
use App\Services\Payments\PaystackClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaystackClient $paystack,
        private readonly EscrowPaymentService $escrowPayments,
    ) {}

    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        if (! $this->paystack->verifyWebhookSignature($raw, $signature)) {
            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();
        $eventType = (string) ($payload['event'] ?? '');
        $eventId = (string) ($payload['id'] ?? $payload['data']['id'] ?? hash('sha256', $raw));

        if ($eventType === '') {
            return response('Ignored', 200);
        }

        try {
            $this->escrowPayments->handleWebhook($eventId, $eventType, $payload);
        } catch (\Throwable $e) {
            report($e);

            return response('Processing error', 500);
        }

        return response('OK', 200);
    }
}
