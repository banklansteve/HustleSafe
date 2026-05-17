<?php

namespace App\Http\Controllers\Operations;

use App\Enums\QuestDisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\QuestDispute;
use App\Support\AdminCsv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsDisputesController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $status = trim((string) $request->input('status', ''));

        $query = QuestDispute::query()
            ->with([
                'quest:id,title,reference_code,slug,uuid',
                'openedBy:id,name,email',
            ]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $disputes = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Operations/Disputes/Index', [
            'disputes' => $disputes,
            'filters' => ['status' => $status, 'per_page' => $perPage],
            'status_options' => collect(QuestDisputeStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => str_replace('_', ' ', ucfirst($s->value)),
            ])->values()->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $status = trim((string) $request->input('status', ''));
        $query = QuestDispute::query()->with(['quest:id,reference_code', 'openedBy:id,email']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $header = [
            'id',
            'uuid',
            'quest_reference',
            'status',
            'phase',
            'opened_by_email',
            'disputed_amount_minor',
            'created_at',
            'resolved_at',
        ];

        return AdminCsv::download('operations-disputes-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($rows) use ($out): void {
                foreach ($rows as $d) {
                    fputcsv($out, [
                        $d->id,
                        $d->uuid,
                        $d->quest?->reference_code,
                        $d->status?->value ?? (string) $d->status,
                        $d->phase?->value ?? (string) $d->phase,
                        $d->openedBy?->email,
                        $d->disputed_amount_minor,
                        $d->created_at?->toIso8601String(),
                        $d->resolved_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
