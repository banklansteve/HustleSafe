<?php

namespace App\Http\Controllers\Operations;

use App\Enums\PortfolioStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\UpdateOperationsPortfolioVisibilityRequest;
use App\Models\Portfolio;
use App\Services\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsPortfoliosController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $q = trim((string) $request->input('q', ''));

        $query = Portfolio::query()
            ->with(['user:id,name,email,slug'])
            ->where('status', PortfolioStatus::Published);

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('slug', 'like', '%'.$q.'%')
                    ->orWhereHas('user', function ($uq) use ($q): void {
                        $uq->where('email', 'like', '%'.$q.'%')
                            ->orWhere('name', 'like', '%'.$q.'%');
                    });
            });
        }

        $portfolios = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Operations/Portfolios/Index', [
            'portfolios' => $portfolios,
            'filters' => ['q' => $q, 'per_page' => $perPage],
        ]);
    }

    public function updateVisibility(UpdateOperationsPortfolioVisibilityRequest $request, Portfolio $portfolio, AdminActivityLogger $logger): RedirectResponse
    {
        $hidden = (bool) $request->validated('admin_hidden');
        $portfolio->forceFill(['admin_hidden' => $hidden])->save();

        $logger->log(
            actor: $request->user(),
            action: $hidden ? 'operations.portfolio_hidden' : 'operations.portfolio_shown',
            subjectType: Portfolio::class,
            subjectId: $portfolio->id,
            properties: ['title' => $portfolio->title],
            request: $request,
        );

        return back()->with('success', $hidden
            ? __('Portfolio hidden from public discovery.')
            : __('Portfolio is visible again.'));
    }
}
