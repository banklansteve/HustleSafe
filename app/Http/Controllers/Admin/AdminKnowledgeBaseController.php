<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffKnowledgeArticle;
use App\Services\Operations\StaffKnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminKnowledgeBaseController extends Controller
{
    public function __construct(private readonly StaffKnowledgeBaseService $service) {}

    public function index(): Response
    {
        return Inertia::render('Admin/KnowledgeBase/Index', [
            'articles' => StaffKnowledgeArticle::query()->orderBy('category')->orderBy('title')->get(['id', 'title', 'slug', 'category', 'status', 'updated_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string'],
            'status' => ['nullable', 'in:published,draft'],
        ]);

        $this->service->storeArticle($request->user(), $data);

        return back()->with('success', 'Article created.');
    }

    public function update(Request $request, StaffKnowledgeArticle $article): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', 'string', 'max:80'],
            'body' => ['sometimes', 'string'],
            'status' => ['nullable', 'in:published,draft'],
        ]);

        $this->service->updateArticle($article, $request->user(), $data);

        return back()->with('success', 'Article updated.');
    }
}
