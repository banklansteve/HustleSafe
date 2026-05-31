<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Quest\QuestJourneySurveyInsightsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminQuestJourneySurveyController extends Controller
{
    public function __construct(private readonly QuestJourneySurveyInsightsService $insights) {}

    public function insights(): Response
    {
        return Inertia::render('Admin/JourneySurveys/Insights', [
            'panel' => $this->insights->dashboardPanel(),
        ]);
    }

    public function index(Request $request): Response
    {
        $userId = $request->integer('user_id') ?: null;
        $cohort = $request->string('cohort')->toString() ?: null;
        $search = $request->string('search')->toString() ?: null;

        $listing = $this->insights->userFeedbackListing($userId, $cohort, $search);

        return Inertia::render('Admin/JourneySurveys/Index', [
            'responses' => $listing['data'],
            'filters' => $listing['filters'],
            'cohorts' => [
                ['value' => 'client_completed', 'label' => 'Client · quest complete'],
                ['value' => 'freelancer_awarded', 'label' => 'Freelancer · paid'],
                ['value' => 'freelancer_rejected', 'label' => 'Freelancer · not selected'],
            ],
        ]);
    }

    public function freeTextSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->string('q')->toString() ?: null;

        return response()->json([
            'items' => $this->insights->recentFreeText(limit: 30, search: $search),
        ]);
    }
}
