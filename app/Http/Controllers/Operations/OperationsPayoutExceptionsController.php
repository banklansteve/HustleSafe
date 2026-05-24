<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffPaymentException;
use App\Services\Operations\StaffPayoutExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsPayoutExceptionsController extends Controller
{
    public function __construct(private readonly StaffPayoutExceptionService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/PayoutExceptions/Index');
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->service->listing($request->user(), $request));
    }

    public function detail(Request $request, StaffPaymentException $exception): JsonResponse
    {
        return response()->json($this->service->detail($exception, $request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:48'],
            'quest_id' => ['nullable', 'integer', 'exists:quests,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'amount_minor' => ['nullable', 'integer', 'min:0'],
            'error_summary' => ['nullable', 'string', 'max:2000'],
            'staff_summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = $this->service->record($request->user(), $data, $request);

        return response()->json(['message' => 'Exception logged.', 'id' => $row->id]);
    }

    public function resolve(Request $request, StaffPaymentException $exception): JsonResponse
    {
        $data = $request->validate(['summary' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->resolve($exception, $request->user(), $data, $request);

        return response()->json(['message' => 'Exception resolved.']);
    }
}
