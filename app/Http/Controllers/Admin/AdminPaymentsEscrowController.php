<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentEscrow;
use App\Models\User;
use App\Services\Admin\PaymentsEscrowAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPaymentsEscrowController extends Controller
{
    public function __construct(private readonly PaymentsEscrowAdminService $service) {}

    public function index(Request $request): Response
    {
        $tab = (string) $request->query('tab', 'escrows');
        if (! in_array($tab, ['escrows', 'wallets', 'transactions'], true)) {
            $tab = 'escrows';
        }

        return Inertia::render('Admin/PaymentsEscrow/Index', [
            'tab' => $tab,
            'summary' => $this->service->dashboardSummary(),
            'escrows' => fn () => $this->service->escrowList($request),
            'wallets' => fn () => $this->service->walletList($request),
            'transactions' => fn () => $this->service->transactionList($request),
            'status_options' => ['pending', 'funded', 'held', 'released', 'refunded', 'cancelled'],
            'transaction_types' => ['funding', 'escrow_hold', 'escrow_release', 'withdrawal', 'withdrawal_reversal', 'fee'],
        ]);
    }

    public function forceRelease(Request $request, PaymentEscrow $escrow): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $updated = $this->service->forceRelease($escrow, $request->user(), $data['reason']);

        return response()->json(['ok' => true, 'escrow' => ['id' => $updated->id, 'status' => $updated->status]]);
    }

    public function forceRefund(Request $request, PaymentEscrow $escrow): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $updated = $this->service->forceRefund($escrow, $request->user(), $data['reason']);

        return response()->json(['ok' => true, 'escrow' => ['id' => $updated->id, 'status' => $updated->status]]);
    }

    public function lockWallet(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $wallet = $this->service->lockWallet($user, $request->user(), $data['reason']);

        return response()->json(['ok' => true, 'wallet' => ['id' => $wallet->id, 'status' => $wallet->status]]);
    }

    public function unlockWallet(User $user): JsonResponse
    {
        $wallet = $this->service->unlockWallet($user);

        return response()->json(['ok' => true, 'wallet' => ['id' => $wallet->id, 'status' => $wallet->status]]);
    }
}
