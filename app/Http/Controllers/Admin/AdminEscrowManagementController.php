<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialEscrowRecord;
use App\Services\Admin\AdminEscrowManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEscrowManagementController extends Controller
{
    public function __construct(
        private readonly AdminEscrowManagementService $escrow,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return Inertia::render('Admin/EscrowManagement/Index', [
            'dashboard' => $this->escrow->dashboard($request),
        ]);
    }

    public function dashboardApi(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return response()->json($this->escrow->dashboard($request));
    }

    public function recordShow(Request $request, FinancialEscrowRecord $record): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return response()->json($this->escrow->recordDetail($record));
    }

    public function reconciliation(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return response()->json($this->escrow->reconciliationSnapshot());
    }
}
