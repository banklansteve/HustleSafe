<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shared\UserVerificationDocumentController;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Operations\StaffVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsVerificationsController extends Controller
{
    public function __construct(private readonly StaffVerificationService $service) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Operations/Verifications/Index', [
            'decision_reasons' => app(\App\Services\Verification\VerificationDecisionReasonService::class)->options(),
            'queue_defaults' => [
                'tab' => 'my_assignments',
                'range' => '30d',
                'per_page' => (int) config('operations.verification_queue.per_page', 25),
            ],
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        /** @var User $staff */
        $staff = $request->user();
        $paginator = $this->service->paginatedListing($request, $staff);

        return response()->json([
            'items' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function detail(Request $request, UserVerification $verification): JsonResponse
    {
        return response()->json($this->service->detail($verification, $request->user()));
    }

    public function decide(Request $request, UserVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,request_corrections'],
            'reason_code' => ['required_unless:action,approve', 'nullable', 'string', 'max:40'],
            'reason_note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->service->decide($verification, $request->user(), $data, $request);

        return response()->json($result);
    }

    public function document(Request $request, UserVerification $verification): StreamedResponse
    {
        return app(UserVerificationDocumentController::class)($request, $verification);
    }

    public function escalate(Request $request, UserVerification $verification): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);

        $result = $this->service->escalate($verification, $request->user(), $data, $request);

        return response()->json($result);
    }
}
