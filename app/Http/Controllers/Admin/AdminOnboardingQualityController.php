<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StoreOnboardingQualityActionRequest;
use App\Models\OnboardingQualityReview;
use App\Services\Onboarding\OnboardingQualityControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOnboardingQualityController extends Controller
{
    public function __construct(private readonly OnboardingQualityControlService $service) {}

    public function index(): Response
    {
        return Inertia::render('Admin/OnboardingQuality/Index', [
            'console' => 'admin',
        ]);
    }

    public function flaggedProfiles(): Response
    {
        return Inertia::render('Admin/OnboardingQuality/FlaggedProfiles', [
            'console' => 'admin',
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
        $this->service->applyAction($request->user(), $review, $request->validated(), superAdminConsole: true);

        return response()->json([
            'message' => 'Action recorded.',
            'detail' => $this->service->detail($review->refresh()),
        ]);
    }
}
