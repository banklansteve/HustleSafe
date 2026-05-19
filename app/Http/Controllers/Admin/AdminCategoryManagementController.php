<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkQuestCategoryRequest;
use App\Http\Requests\Admin\ImportQuestCategoriesRequest;
use App\Http\Requests\Admin\ReorderQuestCategoriesRequest;
use App\Http\Requests\Admin\StoreQuestCategoryRequest;
use App\Models\QuestCategory;
use App\Services\Admin\CategoryManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCategoryManagementController extends Controller
{
    public function __construct(private readonly CategoryManagementService $categories) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categoryManagement' => fn () => $this->categories->overview(),
        ]);
    }

    public function store(StoreQuestCategoryRequest $request): RedirectResponse
    {
        $this->categories->save($request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Category saved.');
    }

    public function update(StoreQuestCategoryRequest $request, QuestCategory $category): RedirectResponse
    {
        $this->categories->save($request->validated(), (int) $request->user()->id, $category);

        return back()->with('success', 'Category updated.');
    }

    public function hide(QuestCategory $category): RedirectResponse
    {
        $this->categories->hide($category);

        return back()->with('success', 'Category hidden.');
    }

    public function archive(QuestCategory $category): RedirectResponse
    {
        $this->categories->archive($category);

        return back()->with('success', 'Category archived.');
    }

    public function restore(QuestCategory $category): RedirectResponse
    {
        $this->categories->restore($category);

        return back()->with('success', 'Category restored.');
    }

    public function reorder(ReorderQuestCategoriesRequest $request): JsonResponse
    {
        return response()->json($this->categories->reorder(
            $request->validated('items'),
            (bool) ($request->validated('confirm_move') ?? false),
        ));
    }

    public function undoReorder(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        $data = $request->validate(['token' => ['required', 'string']]);
        $this->categories->undoReorder($data['token']);

        return response()->json(['ok' => true]);
    }

    public function performance(Request $request, QuestCategory $category): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return response()->json($this->categories->performance($category, (int) $request->query('days', 30)));
    }

    public function bulk(BulkQuestCategoryRequest $request): JsonResponse
    {
        return response()->json($this->categories->bulk($request->validated()));
    }

    public function import(ImportQuestCategoriesRequest $request): JsonResponse
    {
        return response()->json($this->categories->importPreview(
            $request->file('csv'),
            $request->boolean('commit'),
            (int) $request->user()->id,
        ));
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['parent_category_name', 'subcategory_name', 'description', 'icon_name', 'client_fee_percent', 'freelancer_fee_percent', 'status']);
            fputcsv($out, ['Technology', 'Web Development', 'Websites, web apps, and frontend/backend engineering', 'code', '5', '10', 'active']);
            fclose($out);
        }, 'category-import-template.csv');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:quest_categories,id']]);

        return $this->categories->exportSelected($data['ids']);
    }

    public function unique(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'field' => ['required', 'in:name,slug'],
            'value' => ['required', 'string', 'max:120'],
            'ignore_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
        ]);

        return response()->json($this->categories->uniqueCheck(
            $data['parent_id'] ?? null,
            $data['field'],
            $data['value'],
            $data['ignore_id'] ?? null,
        ));
    }
}
