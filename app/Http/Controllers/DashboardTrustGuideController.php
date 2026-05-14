<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardTrustGuideController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('role');
        $slug = $user->role?->slug ?? 'client';

        if ($slug === 'freelancer') {
            return Inertia::render('Dashboard/TrustGuideFreelancer', [
                'copy' => [
                    'title' => __('Raise your trust score'),
                    'intro' => __('Your score blends delivery history, reviews, profile strength, and verified identity. Small, honest steps compound — sponsors see the difference.'),
                ],
                'sections' => $this->freelancerSections(),
            ]);
        }

        if (in_array($slug, ['admin', 'super_admin'], true)) {
            abort(404);
        }

        return Inertia::render('Dashboard/TrustGuideClient', [
            'copy' => [
                'title' => __('Strengthen sponsor trust'),
                'intro' => __('Clients with complete profiles and verified contacts move faster with freelancers. Your score reflects how reliably you brief, fund, and close work.'),
            ],
            'sections' => $this->clientSections(),
        ]);
    }

    /**
     * @return list<array{heading: string, items: list<array{title: string, body: string, href?: string, cta?: string}>}>
     */
    protected function freelancerSections(): array
    {
        return [
            [
                'heading' => __('Identity & compliance'),
                'items' => [
                    [
                        'title' => __('Government ID & NIN'),
                        'body' => __('Upload a clear ID and your National Identification Number where requested. Verified identity is a strong signal of seriousness.'),
                        'href' => route('verifications.index'),
                        'cta' => __('Open verifications'),
                    ],
                    [
                        'title' => __('Proof of address'),
                        'body' => __('A recent utility bill or bank statement helps us match you to your region and reduces fraud risk for everyone.'),
                        'href' => route('verifications.index'),
                        'cta' => __('Add documents'),
                    ],
                ],
            ],
            [
                'heading' => __('Profile & matching'),
                'items' => [
                    [
                        'title' => __('Complete your profile'),
                        'body' => __('Headline, bio, rates, and city help sponsors understand you — and help us rank the right quests for you.'),
                        'href' => route('account.security.edit'),
                        'cta' => __('Edit profile'),
                    ],
                    [
                        'title' => __('Quest categories & subcategories'),
                        'body' => __('Pick the services you actually deliver. Better categories mean better matches and fewer wasted pitches.'),
                        'href' => route('account.security.edit'),
                        'cta' => __('Update categories'),
                    ],
                ],
            ],
            [
                'heading' => __('Delivery & reputation'),
                'items' => [
                    [
                        'title' => __('Ship milestones on time'),
                        'body' => __('Consistent delivery and clear updates reduce disputes and lift your score over time.'),
                    ],
                    [
                        'title' => __('Ask for reviews after great work'),
                        'body' => __('Polite follow-ups after completed quests help you collect fair ratings — quality beats volume.'),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array{heading: string, items: list<array{title: string, body: string, href?: string, cta?: string}>}>
     */
    protected function clientSections(): array
    {
        return [
            [
                'heading' => __('Verification & contact'),
                'items' => [
                    [
                        'title' => __('Verify your organisation'),
                        'body' => __('Company or sponsor verification where available helps freelancers trust your briefs and escrow.'),
                        'href' => route('verifications.index'),
                        'cta' => __('Verifications'),
                    ],
                    [
                        'title' => __('Keep billing details current'),
                        'body' => __('Accurate contacts reduce delays when milestones need approval or clarification.'),
                        'href' => route('account.security.edit'),
                        'cta' => __('Account settings'),
                    ],
                ],
            ],
            [
                'heading' => __('How you work with talent'),
                'items' => [
                    [
                        'title' => __('Write clear quest briefs'),
                        'body' => __('Scope, deadlines, and budget ranges reduce back-and-forth and help freelancers quote accurately.'),
                        'href' => route('quests.create'),
                        'cta' => __('Create a quest'),
                    ],
                    [
                        'title' => __('Respond to proposals promptly'),
                        'body' => __('Timely replies keep good freelancers engaged and improve how the community rates the experience.'),
                        'href' => route('dashboard.lists.show', ['list' => 'client-proposals-inbox']),
                        'cta' => __('View proposals'),
                    ],
                ],
            ],
        ];
    }
}
