<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    public function terms(): Response
    {
        return Inertia::render('Legal/Document', [
            'title' => __('Terms of Service'),
            'subtitle' => __('Last updated: May 2026'),
            'paragraphs' => [
                __('By using HustleSafe you agree to operate honestly, keep payments on-platform unless we explicitly allow otherwise, and follow applicable laws in Nigeria and your own jurisdiction.'),
                __('Clients agree to fund escrow promptly, give clear briefs, and resolve disputes through our dispute process. Freelancers agree to deliver work as described, communicate professionally, and not circumvent platform fees.'),
                __('When a job reaches its agreed end date, escrow funds are released to the freelancer after 72 hours unless you explicitly mark the work as completed sooner or open a dispute within that window. Marking complete is how you confirm delivery — this automatic release protects freelancers from indefinite holds while still giving you a short review period (full operational detail will appear in the FAQ and escrow policy as we expand documentation).'),
                __('We may update these terms; continued use after notice constitutes acceptance. If you do not agree, stop using the service.'),
            ],
        ]);
    }

    public function privacy(): Response
    {
        return Inertia::render('Legal/Document', [
            'title' => __('Privacy Policy'),
            'subtitle' => __('Last updated: May 2026'),
            'paragraphs' => [
                __('We collect account, profile, and transaction data needed to run the marketplace, prevent fraud, and meet legal obligations.'),
                __('We do not sell your personal data. Service providers may process data under strict agreements. You may request account export or deletion subject to legal retention rules.'),
                __('Cookies and similar technologies help keep you signed in and improve reliability. You can control cookies in your browser.'),
            ],
        ]);
    }
}
