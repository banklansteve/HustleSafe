<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StorePaymentReviewFlagRequest;
use App\Services\Payments\PaymentMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsPaymentMonitoringController extends Controller
{
    public function __construct(private readonly PaymentMonitoringService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/PaymentMonitoring/Index');
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->service->queue($request));
    }

    public function flag(StorePaymentReviewFlagRequest $request): JsonResponse
    {
        $flag = $this->service->raiseFlag($request->user(), $request->validated());

        return response()->json([
            'message' => 'Payment review flag submitted to Super Admin financial queue.',
            'flag' => [
                'id' => $flag->id,
                'anomaly_fingerprint' => $flag->anomaly_fingerprint,
            ],
        ]);
    }
}
