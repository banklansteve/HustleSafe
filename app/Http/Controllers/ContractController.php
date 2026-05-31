<?php

namespace App\Http\Controllers;

use App\Models\QuestContract;
use App\Services\Contracts\ContractEventLogger;
use App\Services\Contracts\ContractPresentationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ContractController extends Controller
{
    public function index(Request $request, ContractPresentationService $presentation): Response
    {
        return Inertia::render('Contracts/Index', [
            'contracts' => $presentation->indexRows($request->user()),
        ]);
    }

    public function show(Request $request, QuestContract $contract, ContractPresentationService $presentation): Response
    {
        $this->authorize('view', $contract);

        $isAdmin = in_array($request->user()?->role?->slug, ['admin', 'super_admin'], true);

        return Inertia::render('Contracts/Show', $presentation->showPayload($contract, $request->user(), $isAdmin));
    }

    public function pdf(Request $request, QuestContract $contract, ContractEventLogger $events): HttpResponse
    {
        $this->authorize('downloadPdf', $contract);

        $contract->load(['deliverables', 'milestones', 'amendments']);

        $events->log($contract, 'contract.pdf_downloaded', $request->user(), [], $request);

        $html = view('pdf.quest-contract', [
            'contract' => $contract,
            'terms' => $contract->effectiveTerms(),
            'platformName' => config('app.name', 'HustleSafe'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return $pdf->download($contract->reference_code.'.pdf');
    }
}
