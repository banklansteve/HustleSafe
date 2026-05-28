<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ResolvePaymentReviewFlagRequest;
use App\Models\PaymentReviewFlag;
use App\Services\Payments\PaymentFinancialReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFinancialReviewController extends Controller
{
    public function __construct(private readonly PaymentFinancialReviewService $service) {}

    public function index(): Response
    {
        return Inertia::render('Admin/FinancialReview/Index');
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->service->listing($request));
    }

    public function resolve(ResolvePaymentReviewFlagRequest $request, PaymentReviewFlag $flag): JsonResponse
    {
        $updated = $this->service->resolve($request->user(), $flag, $request->validated());

        return response()->json([
            'message' => 'Flag updated.',
            'flag' => ['id' => $updated->id, 'resolution_status' => $updated->resolution_status],
        ]);
    }
}
