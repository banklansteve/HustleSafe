<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\RevenueMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRevenueMonitorController extends Controller
{
    public function __construct(
        private readonly RevenueMonitorService $revenue,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/RevenueMonitor/Index', $this->revenue->indexPayload($request));
    }

    public function listing(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        return response()->json($this->revenue->indexPayload($request)['transactions']);
    }

    public function detail(Request $request, string $type, int $id): JsonResponse
    {
        return response()->json($this->revenue->transactionDetail($type, $id));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        return $this->revenue->exportCsv($request);
    }

    public function exportPdf(Request $request)
    {
        return $this->revenue->exportPdf($request);
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $payload = $this->revenue->indexPayload($request);

        return [
            \Carbon\Carbon::parse($payload['period']['from'])->startOfDay(),
            \Carbon\Carbon::parse($payload['period']['to'])->endOfDay(),
        ];
    }
}
