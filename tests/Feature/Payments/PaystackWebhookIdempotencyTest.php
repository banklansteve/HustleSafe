<?php

namespace Tests\Feature\Payments;

use App\Models\PaystackWebhookEvent;
use App\Services\Payments\EscrowPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaystackWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_already_processed_webhook_is_skipped(): void
    {
        PaystackWebhookEvent::query()->create([
            'event_id' => 'evt_123',
            'event_type' => 'charge.success',
            'reference' => 'ref_1',
            'payload' => ['event' => 'charge.success'],
            'processed_at' => now(),
            'processing_result' => 'ok',
        ]);

        app(EscrowPaymentService::class)->handleWebhook(
            'evt_123',
            'charge.success',
            ['event' => 'charge.success', 'data' => ['reference' => 'ref_1']],
        );

        $this->assertSame(1, PaystackWebhookEvent::query()->where('event_id', 'evt_123')->count());
    }
}
