<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Services\Payments\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_and_debit_maintain_balance(): void
    {
        $user = User::factory()->create();

        $service = app(WalletService::class);
        $service->credit($user, 50000, 'escrow_release', 'test:credit:1');
        $service->debit($user, 20000, 'withdrawal', 'test:debit:1');

        $wallet = $service->ensureWallet($user->fresh());
        $this->assertSame(30000, (int) $wallet->balance_minor);
    }

    public function test_idempotent_credit_does_not_double_balance(): void
    {
        $user = User::factory()->create();
        $service = app(WalletService::class);

        $service->credit($user, 10000, 'escrow_release', 'idem:1');
        $service->credit($user, 10000, 'escrow_release', 'idem:1');

        $this->assertSame(10000, (int) $service->ensureWallet($user->fresh())->balance_minor);
    }
}
