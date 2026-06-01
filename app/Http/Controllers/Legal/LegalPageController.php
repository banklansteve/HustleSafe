<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Support\Legal\LegalDocumentPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LegalPageController extends Controller
{
    public function __construct(private readonly LegalDocumentPresenter $documents) {}

    public function terms(): Response
    {
        return $this->render('terms');
    }

    public function privacy(): Response
    {
        return $this->render('privacy');
    }

    public function escrow(): Response
    {
        return $this->render('escrow');
    }

    public function dispute(): Response
    {
        return $this->render('dispute');
    }

    public function termsPdf(): HttpResponse
    {
        return $this->pdf('terms');
    }

    public function privacyPdf(): HttpResponse
    {
        return $this->pdf('privacy');
    }

    public function escrowPdf(): HttpResponse
    {
        return $this->pdf('escrow');
    }

    public function disputePdf(): HttpResponse
    {
        return $this->pdf('dispute');
    }

    private function render(string $key): Response
    {
        return Inertia::render('Legal/PolicyDocument', [
            'document' => $this->documents->build($key),
        ]);
    }

    private function pdf(string $key): HttpResponse
    {
        $document = $this->documents->build($key);

        $html = view('pdf.legal-document', [
            'document' => $document,
            'platformName' => config('app.name', 'HustleSafe'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $slug = str($document['title'])->slug()->toString();

        return $pdf->download($slug.'.pdf');
    }
}
