<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffKnowledgeArticle;
use App\Services\Operations\StaffKnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsKnowledgeBaseController extends Controller
{
    public function __construct(private readonly StaffKnowledgeBaseService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/KnowledgeBase/Index');
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->service->listing(
            $request->input('q'),
            $request->input('category'),
        ));
    }

    public function article(StaffKnowledgeArticle $article): JsonResponse
    {
        return response()->json($this->service->article($article));
    }

    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'article_id' => ['nullable', 'integer', 'exists:staff_knowledge_articles,id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $this->service->suggest($request->user(), $data);

        return response()->json(['message' => 'Suggestion submitted. Super Admins will review it.']);
    }
}
