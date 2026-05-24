<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Operations\StaffFreelancerQualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsQualityController extends Controller
{
    public function __construct(private readonly StaffFreelancerQualityService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Quality/Index');
    }

    public function listing(): JsonResponse
    {
        return response()->json($this->service->listing());
    }

    public function detail(User $freelancer): JsonResponse
    {
        return response()->json($this->service->detail($freelancer));
    }

    public function contact(Request $request, User $freelancer): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'channel' => ['nullable', 'in:both,email,in_app'],
        ]);
        $this->service->contact($request->user(), $freelancer, $data, $request);

        return response()->json(['message' => 'Coaching message sent.']);
    }

    public function warning(Request $request, User $freelancer): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->warning($request->user(), $freelancer, $data, $request);

        return response()->json(['message' => 'Performance warning recorded.']);
    }

    public function restrict(Request $request, User $freelancer): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->restrictHighValue($request->user(), $freelancer, $data, $request);

        return response()->json(['message' => 'High-value bid restriction noted.']);
    }

    public function refer(Request $request, User $freelancer): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->referForReview($request->user(), $freelancer, $data, $request);

        return response()->json(['message' => 'Referred for account review.']);
    }
}
