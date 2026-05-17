<?php

namespace App\Http\Controllers\Operations;

use App\Enums\QuestStatus;
use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Support\AdminCsv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsPaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));

        $query = Quest::query()
            ->with(['client:id,name,email', 'freelancer:id,name,email']);

        $escrow = trim((string) $request->input('escrow', ''));
        if ($escrow !== '') {
            $query->where('escrow_status', $escrow);
        }

        $quests = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        $escrowOptions = Quest::query()
            ->whereNotNull('escrow_status')
            ->where('escrow_status', '!=', '')
            ->groupBy('escrow_status')
            ->orderBy('escrow_status')
            ->pluck('escrow_status')
            ->values()
            ->all();

        return Inertia::render('Operations/Payments/Index', [
            'quests' => $quests,
            'filters' => ['escrow' => $escrow, 'per_page' => $perPage],
            'escrow_options' => $escrowOptions,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Quest::query()->with(['client:id,email', 'freelancer:id,email']);

        $escrow = trim((string) $request->input('escrow', ''));
        if ($escrow !== '') {
            $query->where('escrow_status', $escrow);
        }

        $header = [
            'id',
            'reference_code',
            'title',
            'status',
            'escrow_status',
            'budget_amount_minor',
            'paid_out_minor',
            'client_email',
            'freelancer_email',
            'escrow_funded_at',
            'updated_at',
        ];

        return AdminCsv::download('operations-escrow-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($quests) use ($out): void {
                foreach ($quests as $quest) {
                    fputcsv($out, [
                        $quest->id,
                        $quest->reference_code,
                        $quest->title,
                        $quest->status instanceof QuestStatus ? $quest->status->value : (string) $quest->status,
                        $quest->escrow_status,
                        $quest->budget_amount_minor,
                        $quest->paid_out_minor,
                        $quest->client?->email,
                        $quest->freelancer?->email,
                        $quest->escrow_funded_at?->toIso8601String(),
                        $quest->updated_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
