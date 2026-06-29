<?php

namespace App\Http\Controllers;

use App\Http\Requests\Disputes\StoreDisputeResolutionRequest;
use App\Models\QuestDispute;
use App\Enums\DisputeResolutionOption;
use App\Services\Disputes\DisputeResolutionMatrixService;
use App\Services\Disputes\DisputeResolutionRequestService;
use Illuminate\Http\RedirectResponse;

class QuestDisputeResolutionController extends Controller
{
    public function store(
        StoreDisputeResolutionRequest $request,
        QuestDispute $dispute,
        DisputeResolutionRequestService $service,
    ): RedirectResponse {
        $this->authorize('participate', $dispute);

        $validated = $request->validated();
        $proposal = $service->propose($request->user(), $dispute, $validated);
        $option = DisputeResolutionOption::from((string) $validated['option']);

        $message = match (true) {
            $option === DisputeResolutionOption::SplitFund => __('Your payment split was sent to the other party. The dispute stays open until they accept.'),
            $proposal->status === 'matched' => __('You both agreed on the same outcome. Customer Support will review and close the case.'),
            app(DisputeResolutionMatrixService::class)->resolutionPath($option) === 'together'
                => __('Your proposal was sent to the other party. The dispute stays open until you both agree.'),
            default => __('Your request was sent to Customer Support. The dispute stays open until they review it.'),
        };

        return back()->with('success', $message);
    }
}
