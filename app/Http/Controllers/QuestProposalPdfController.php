<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\QuestOffer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class QuestProposalPdfController extends Controller
{
    public function __invoke(Request $request, Quest $quest, QuestOffer $offer): Response
    {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->authorize('downloadPdf', $offer);

        $quest->loadMissing(['client:id,name', 'questCategory:id,name', 'questCategory.parent:id,name', 'stateModel:id,name', 'localGovernment:id,name']);
        $offer->loadMissing(['freelancer:id,name,first_name,headline']);

        $html = view('pdf.quest-proposal', [
            'quest' => $quest,
            'offer' => $offer,
            'pitchHtml' => Str::markdown($offer->pitch ?? ''),
            'scopeHtml' => Str::markdown($offer->scope_detail ?? ''),
            'warrantyHtml' => $offer->warranty_terms ? Str::markdown($offer->warranty_terms) : null,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $filename = 'hustlesafe-proposal-'.Str::slug($quest->title).'-'.$offer->id.'.pdf';

        return $pdf->download($filename);
    }
}
