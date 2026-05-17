<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Services\Admin\FinancialControlCentreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFinancialControlController extends Controller
{
    public function __construct(private readonly FinancialControlCentreService $finance) {}

    public function index(Request $request): Response
    {
        $section = (string) $request->query('section', 'escrow');

        return Inertia::render('Admin/Financial/Index', [
            'section' => in_array($section, ['escrow', 'revenue', 'ledger', 'payouts', 'refunds'], true) ? $section : 'escrow',
            'summary' => $this->finance->summary(),
            'escrow' => fn () => $this->finance->escrowPage($request),
            'revenue' => fn () => $this->finance->revenuePage(),
            'ledger' => fn () => $this->finance->ledgerPage($request),
            'payouts' => fn () => [
                'queue' => [],
                'message' => 'Payout rail integration is scaffolded here; completed admin releases and payout ledger entries will appear in this queue.',
            ],
            'refunds' => fn () => [
                'queue' => [],
                'message' => 'Refund review workflows are scaffolded here; refund ledger entries and disputed escrows will appear in this queue.',
            ],
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->finance->summary());
    }

    public function escrowLedger(Quest $quest): JsonResponse
    {
        return response()->json($this->finance->escrowLedger($quest));
    }

    public function escrowAction(Quest $quest, Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:manual_release,manual_hold,freeze,unfreeze,full_refund,partial_refund'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'freelancer_amount' => ['nullable', 'numeric', 'min:0'],
            'milestone' => ['nullable', 'string', 'max:160'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'expected_resolution_at' => ['nullable', 'date', 'after:today'],
        ]);

        $entry = $this->finance->applyEscrowAction($quest, $request->user(), $data);

        return response()->json([
            'ok' => true,
            'entry_id' => $entry->id,
            'ledger' => $this->finance->escrowLedger($quest->fresh()),
        ]);
    }
}
