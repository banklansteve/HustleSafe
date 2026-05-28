<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StoreOnboardingQualityActionRequest;
use App\Models\OnboardingQualityReview;
use App\Services\Onboarding\OnboardingQualityControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsOnboardingQualityController extends Controller
{
    public function __construct(private readonly OnboardingQualityControlService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/OnboardingQuality/Index', [
            'console' => 'operations',
        ]);
    }

    public function flaggedProfiles(): Response
    {
        return Inertia::render('Operations/OnboardingQuality/FlaggedProfiles', [
            'console' => 'operations',
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->service->listing($request));
    }

    public function flaggedListing(Request $request): JsonResponse
    {
        return response()->json($this->service->flaggedProfilesListing($request));
    }

    public function detail(OnboardingQualityReview $review): JsonResponse
    {
        return response()->json($this->service->detail($review));
    }

    public function action(StoreOnboardingQualityActionRequest $request, OnboardingQualityReview $review): JsonResponse
    {
        $this->service->applyAction($request->user(), $review, $request->validated(), superAdminConsole: false);

        return response()->json([
            'message' => 'Action recorded.',
            'detail' => $this->service->detail($review->refresh()),
        ]);
    }
}
