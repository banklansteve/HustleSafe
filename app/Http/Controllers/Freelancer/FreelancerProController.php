<?php

namespace App\Http\Controllers\Freelancer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Freelancer\BeginFreelancerProUpgradeRequest;
use App\Services\Freelancer\FreelancerProPaymentService;
use App\Services\Freelancer\FreelancerProSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FreelancerProController extends Controller
{
    public function __construct(
        private readonly FreelancerProSubscriptionService $subscriptions,
        private readonly FreelancerProPaymentService $payments,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Freelancer/Pro/Index', [
            'pro' => $this->subscriptions->dashboardPayload($request->user()),
        ]);
    }

    public function upgrade(BeginFreelancerProUpgradeRequest $request): RedirectResponse
    {
        $payload = $this->subscriptions->beginUpgrade(
            $request->user(),
            (string) $request->validated('billing_cycle'),
        );

        if ($payload['stub_mode'] ?? false) {
            $this->payments->verifyAndActivate($payload['reference']);

            return redirect()->route('freelancer.pro.index')->with('success', __('Pro membership activated (sandbox mode).'));
        }

        return Inertia::location($payload['authorization_url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');
        if ($reference === '') {
            return redirect()->route('freelancer.pro.index')->with('error', __('Payment reference missing.'));
        }

        try {
            $this->payments->verifyAndActivate($reference);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('freelancer.pro.index')->with('error', __('Payment could not be verified. Please try again or contact support.'));
        }

        return redirect()->route('freelancer.pro.index')->with('success', __('Welcome to HustleSafe Pro!'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->subscriptions->cancel($request->user(), $request->input('reason'));

        return back()->with('success', __('Your Pro subscription has been cancelled.'));
    }
}
