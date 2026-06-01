<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HelpSearchLog;
use App\Support\Help\HelpArticleCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpContentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $audience = $this->audienceFor($request);
        $articles = HelpArticleCatalog::listing($audience, $query);

        if ($query !== '') {
            HelpSearchLog::query()->create([
                'user_id' => $request->user()?->id,
                'query' => mb_strtolower($query),
                'results_count' => count($articles),
                'audience' => $audience,
            ]);
        }

        return Inertia::render('Public/Help/Index', [
            'articles' => $articles,
            'query' => $query,
            'audience' => $audience,
            'featured_slugs' => HelpArticleCatalog::featuredSlugs(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $article = HelpArticleCatalog::build($slug);
        abort_if($article === null, 404);

        $audience = $this->audienceFor($request);
        if ($article['audience'] !== 'all' && $article['audience'] !== $audience && $request->user() !== null) {
            // Still show — audience is a hint for sorting, not a hard block for logged-in users.
        }

        return Inertia::render('Public/Help/Article', [
            'article' => $article,
        ]);
    }

    private function audienceFor(Request $request): string
    {
        return match ($request->user()?->role?->slug) {
            'client' => 'clients',
            'freelancer' => 'freelancers',
            default => 'all',
        };
    }
}
