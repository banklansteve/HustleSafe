<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $faqItems = __('landing.faq.items');

        return Inertia::render('Public/LandingPage', [
            'seo' => [
                'title' => __('landing.meta.title'),
                'description' => __('landing.meta.description'),
                'keywords' => __('landing.meta.keywords'),
                'canonical' => url('/'),
                'og_title' => __('landing.meta.title'),
                'og_description' => __('landing.meta.description'),
                'og_image' => asset('images/landing/og-banner.svg'),
                'twitter_card' => 'summary_large_image',
            ],
            'structuredData' => $this->structuredData($faqItems),
            'copy' => [
                'nav' => __('landing.nav'),
                'hero' => __('landing.hero'),
                'how_it_works' => __('landing.how_it_works'),
                'trust' => __('landing.trust'),
                'categories' => __('landing.categories'),
                'popular_jobs' => __('landing.popular_jobs'),
                'testimonials' => __('landing.testimonials'),
                'faq' => __('landing.faq'),
                'footer' => __('landing.footer'),
            ],
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ]);
    }

    /**
     * @param  array<int, array{q: string, a: string}>  $faqItems
     * @return array<string, mixed>
     */
    protected function structuredData(array $faqItems): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $name = config('app.name');

        $faqEntities = [];
        foreach ($faqItems as $item) {
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $item['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $baseUrl.'/#organization',
                    'name' => $name,
                    'url' => $baseUrl.'/',
                    'description' => __('landing.meta.description'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $baseUrl.'/favicon.ico',
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $baseUrl.'/#website',
                    'url' => $baseUrl.'/',
                    'name' => $name,
                    'publisher' => ['@id' => $baseUrl.'/#organization'],
                    'inLanguage' => app()->getLocale(),
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $baseUrl.'/#webpage',
                    'url' => $baseUrl.'/',
                    'name' => __('landing.meta.title'),
                    'description' => __('landing.meta.description'),
                    'isPartOf' => ['@id' => $baseUrl.'/#website'],
                    'about' => ['@id' => $baseUrl.'/#organization'],
                    'primaryImageOfPage' => [
                        '@type' => 'ImageObject',
                        'url' => asset('images/landing/og-banner.svg'),
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    '@id' => $baseUrl.'/#faq',
                    'mainEntity' => $faqEntities,
                ],
            ],
        ];
    }
}
