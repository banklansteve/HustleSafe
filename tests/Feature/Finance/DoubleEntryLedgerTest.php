<?php

namespace Tests\Feature\Finance;

use App\Enums\LedgerAccount;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\User;
use App\Services\Finance\DoubleEntryLedgerService;
use App\Services\Finance\FinancialLedgerBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoubleEntryLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_escrow_funding_posts_balanced_ledger_entries(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id, 'freelancer_id' => $freelancer->id]);

        $escrow = PaymentEscrow::query()->create([
            'quest_id' => $quest->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'amount_minor' => 10000000,
            'currency' => 'NGN',
            'status' => 'pending',
            'paystack_reference' => 'TEST-REF-001',
        ]);

        app(FinancialLedgerBridgeService::class)->onEscrowFunded($escrow, 'TEST-REF-001');

        $balance = app(DoubleEntryLedgerService::class)->globalBalanceCheck();
        $this->assertTrue($balance['balanced']);

        $liability = app(DoubleEntryLedgerService::class)->accountBalanceMinor(LedgerAccount::ClientEscrowLiability);
        $this->assertSame(10000000, $liability);
    }

    public function test_escrow_release_splits_fee_vat_and_freelancer_net(): void
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $quest = Quest::factory()->create(['client_id' => $client->id, 'freelancer_id' => $freelancer->id]);

        $escrow = PaymentEscrow::query()->create([
            'quest_id' => $quest->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'amount_minor' => 10000000,
            'currency' => 'NGN',
            'status' => 'funded',
            'funded_at' => now(),
            'paystack_reference' => 'TEST-REF-002',
        ]);

        $bridge = app(FinancialLedgerBridgeService::class);
        $bridge->onEscrowFunded($escrow, 'TEST-REF-002');
        $bridge->onEscrowReleased($escrow, 10000000, 'client_marked_complete', 'WTX-TEST');

        $ledger = app(DoubleEntryLedgerService::class);
        $this->assertTrue($ledger->globalBalanceCheck()['balanced']);
        $this->assertGreaterThan(0, $ledger->accountBalanceMinor(LedgerAccount::PlatformFeeRevenue));
        $this->assertGreaterThan(0, $ledger->accountBalanceMinor(LedgerAccount::VatPayable));
        $this->assertGreaterThan(0, $ledger->accountBalanceMinor(LedgerAccount::FreelancerPayable));
    }
}
