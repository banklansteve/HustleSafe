<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinancialHealthHoldRequest;
use App\Http\Requests\Admin\FinancialHealthInvestigateRequest;
use App\Http\Requests\Admin\FinancialHealthNoteRequest;
use App\Models\FinancialEscrowRecord;
use App\Services\Admin\FinancialHealthDashboardService;
use App\Services\Admin\FinancialHealthTransactionActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFinancialHealthController extends Controller
{
    public function __construct(
        private readonly FinancialHealthDashboardService $dashboard,
        private readonly FinancialHealthTransactionActionService $actions,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/FinancialHealth/Index', $this->dashboard->indexPayload($request));
    }

    public function apiSnapshot(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->apiSnapshot($request));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        return $this->dashboard->exportCsv($request);
    }

    public function exportPdf(Request $request)
    {
        return $this->dashboard->exportPdf($request);
    }

    public function addNote(FinancialHealthNoteRequest $request, FinancialEscrowRecord $record): JsonResponse
    {
        return response()->json(
            $this->actions->addNote($record, $request->user(), $request->validated('note'))
        );
    }

    public function hold(FinancialHealthHoldRequest $request, FinancialEscrowRecord $record): JsonResponse
    {
        return response()->json(
            $this->actions->hold(
                $record,
                $request->user(),
                $request->validated('reason'),
                $request->validated('hold_until'),
            )
        );
    }

    public function liftHold(FinancialHealthHoldRequest $request, FinancialEscrowRecord $record): JsonResponse
    {
        return response()->json(
            $this->actions->liftHold($record, $request->user(), $request->validated('reason'))
        );
    }

    public function investigate(FinancialHealthInvestigateRequest $request, FinancialEscrowRecord $record): JsonResponse
    {
        return response()->json(
            $this->actions->investigate($record, $request->user(), $request->validated('reason'))
        );
    }
}
