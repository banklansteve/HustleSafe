<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPortfolioReviewRequest;
use App\Models\Portfolio;
use App\Services\Admin\AdminPortfolioReviewService;
use App\Services\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPortfolioReviewController extends Controller
{
    public function index(Request $request, AdminPortfolioReviewService $service): Response
    {
        return Inertia::render('Admin/PortfolioReview/Index', [
            'routeNamespace' => $this->routeNamespace($request),
            ...$service->indexPayload($request),
        ]);
    }

    public function show(Request $request, Portfolio $portfolio, AdminPortfolioReviewService $service): Response
    {
        return Inertia::render('Admin/PortfolioReview/Show', [
            'routeNamespace' => $this->routeNamespace($request),
            ...$service->showPayload($portfolio),
        ]);
    }

    public function operationsIndex(Request $request, AdminPortfolioReviewService $service): Response
    {
        return $this->index($request, $service);
    }

    public function operationsShow(Request $request, Portfolio $portfolio, AdminPortfolioReviewService $service): Response
    {
        return $this->show($request, $portfolio, $service);
    }

    public function operationsUpdate(
        UpdateAdminPortfolioReviewRequest $request,
        Portfolio $portfolio,
        AdminPortfolioReviewService $service,
        AdminActivityLogger $logger,
    ): RedirectResponse {
        return $this->update($request, $portfolio, $service, $logger);
    }

    private function routeNamespace(Request $request): string
    {
        return str_starts_with($request->path(), 'operations/') ? 'operations' : 'admin';
    }

    public function update(
        UpdateAdminPortfolioReviewRequest $request,
        Portfolio $portfolio,
        AdminPortfolioReviewService $service,
        AdminActivityLogger $logger,
    ): RedirectResponse {
        $validated = $request->validated();
        $before = [
            'status' => $portfolio->status->value,
            'admin_hidden' => (bool) $portfolio->admin_hidden,
        ];

        $updated = $service->applyReview($portfolio, $validated);

        $logger->log(
            actor: $request->user(),
            action: 'admin.portfolio_review_updated',
            subjectType: Portfolio::class,
            subjectId: $updated->id,
            properties: [
                'title' => $updated->title,
                'before' => $before,
                'after' => [
                    'status' => $updated->status->value,
                    'admin_hidden' => (bool) $updated->admin_hidden,
                ],
                'note' => $validated['note'] ?? null,
            ],
            request: $request,
        );

        return redirect()
            ->route($request->route()?->getName() === 'operations.portfolio-review.update'
                ? 'operations.portfolio-review.show'
                : 'admin.portfolio-review.show', $updated)
            ->with('success', __('Portfolio review saved.'));
    }
}
