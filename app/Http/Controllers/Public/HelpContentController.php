<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HelpSearchLog;
use App\Models\HelpSection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpContentController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $audience = $this->audienceFor($request);
        $sections = HelpSection::query()
            ->where('status', 'active')
            ->with(['faqs' => fn ($q) => $q
                ->where('status', 'active')
                ->whereIn('audience', ['all', $audience])
                ->orderBy('display_order')
                ->orderBy('id')])
            ->orderBy('display_order')
            ->get();

        $payload = $sections
            ->map(fn (HelpSection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'slug' => $section->slug,
                'faqs' => $section->faqs
                    ->filter(fn ($faq) => $query === '' || $this->faqMatches($faq, $query))
                    ->map(fn ($faq) => [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'answer' => $faq->answer,
                        'audience' => $faq->audience,
                    ])
                    ->values(),
            ])
            ->filter(fn ($section) => $query === '' || count($section['faqs']) > 0)
            ->values();

        if ($query !== '') {
            HelpSearchLog::query()->create([
                'user_id' => $request->user()?->id,
                'query' => mb_strtolower($query),
                'results_count' => $payload->sum(fn ($section) => count($section['faqs'])),
                'audience' => $audience,
            ]);
        }

        return Inertia::render('Public/Help/Index', [
            'sections' => $payload,
            'query' => $query,
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

    private function faqMatches($faq, string $query): bool
    {
        $needle = mb_strtolower($query);
        $haystack = mb_strtolower($faq->question.' '.$faq->answer.' '.implode(' ', $faq->search_keywords ?? []));

        return str_contains($haystack, $needle);
    }
}
