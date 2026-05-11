<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\StoreNewsletterSubscriberRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;

class NewsletterController extends Controller
{
    public function store(StoreNewsletterSubscriberRequest $request): RedirectResponse
    {
        NewsletterSubscriber::query()->firstOrCreate(
            ['email' => $request->validated('email')]
        );

        return back()->with('newsletter', __('landing.newsletter.success'));
    }
}
