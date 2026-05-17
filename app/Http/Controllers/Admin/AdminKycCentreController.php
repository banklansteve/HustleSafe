<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKycDecisionRequest;
use App\Http\Requests\Admin\UpdateKycSettingsRequest;
use App\Models\KycDocument;
use App\Models\KycReviewCase;
use App\Services\Admin\KycCentreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminKycCentreController extends Controller
{
    public function __construct(private readonly KycCentreService $kyc) {}

    public function index(Request $request): Response
    {
        $section = (string) $request->query('section', 'queue');
        if (! in_array($section, ['queue', 'analytics', 'settings'], true)) {
            $section = 'queue';
        }

        return Inertia::render('Admin/Kyc/Index', [
            'section' => $section,
            'summary' => fn () => $this->kyc->summary(),
            'queue' => fn () => $section === 'queue' ? $this->kyc->queue($request) : ['data' => []],
            'analytics' => fn () => $section === 'analytics' ? $this->kyc->analytics() : null,
            'settings' => fn () => $section === 'settings' ? $this->kyc->settings() : null,
            'filters' => $request->only(['q', 'priority', 'role', 'tier', 'sort', 'per_page']),
            'reasonOptions' => [
                'identity_mismatch',
                'name_mismatch',
                'date_of_birth_mismatch',
                'low_confidence',
                'duplicate_identity',
                'liveness_failed',
                'document_expired',
                'document_illegible',
                'suspected_fraud',
                'cac_review_required',
            ],
            'correctionOptions' => [
                'name_spelling',
                'date_of_birth',
                'document_type',
                'selfie_quality',
                'utility_bill',
                'cac_document',
                'address_mismatch',
            ],
        ]);
    }

    public function show(Request $request, KycReviewCase $case): JsonResponse
    {
        return response()->json($this->kyc->casePayload($case, $request->user(), $request->boolean('documents')));
    }

    public function reveal(Request $request, KycReviewCase $case): JsonResponse
    {
        $request->validate(['field' => ['required', 'string', 'max:40']]);

        return response()->json($this->kyc->reveal($case, $request->user(), (string) $request->input('field')));
    }

    public function document(Request $request, KycDocument $document): StreamedResponse
    {
        return $this->kyc->streamDocument($document, $request->user());
    }

    public function decide(StoreKycDecisionRequest $request, KycReviewCase $case): JsonResponse
    {
        $decision = $this->kyc->decide($case, $request->user(), $request->validated());

        return response()->json(['ok' => true, 'decision_id' => $decision->id]);
    }

    public function updateSettings(UpdateKycSettingsRequest $request): RedirectResponse
    {
        $this->kyc->updateSettings($request->validated());

        return back()->with('success', 'KYC settings saved.');
    }
}
