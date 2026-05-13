<?php

namespace App\Http\Controllers;

use App\Enums\PortfolioStatus;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FreelancerPortfoliosDirectoryController extends Controller
{
    public function __invoke(Request $request, string $slug): Response
    {
        $profile = $this->resolveFreelancer($slug);
        $settings = $profile->effectivePublicProfileSettings();

        if (! ($settings['show_portfolio'] ?? true)) {
            throw new NotFoundHttpException;
        }

        $q = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'latest');

        $query = Portfolio::query()
            ->where('user_id', $profile->id)
            ->where('status', PortfolioStatus::Published)
            ->where('admin_hidden', false);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }

        match ($sort) {
            'popular' => $query->orderByDesc('favorites_count')->orderByDesc('published_at'),
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        $portfolios = $query->paginate(12)->withQueryString();

        return Inertia::render('Profile/PublicPortfolios', [
            'profile' => [
                'slug' => $profile->slug,
                'name' => $profile->first_name ?: $profile->name,
                'avatar_url' => $profile->avatar_url,
            ],
            'portfolios' => $portfolios->through(fn (Portfolio $p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'description_excerpt' => str($p->description)->limit(140)->toString(),
                'cover_url' => $p->coverUrl(),
                'favorites_count' => (int) $p->favorites_count,
                'published_at' => $p->published_at?->timezone('Africa/Lagos')->toIso8601String(),
            ]),
            'filters' => [
                'q' => $q,
                'sort' => $sort,
            ],
        ]);
    }

    protected function resolveFreelancer(string $slug): User
    {
        $user = User::query()
            ->where('slug', $slug)
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->first();

        if ($user === null) {
            throw new NotFoundHttpException;
        }

        return $user;
    }
}
