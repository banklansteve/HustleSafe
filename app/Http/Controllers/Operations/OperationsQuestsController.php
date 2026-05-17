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

class OperationsQuestsController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));

        $query = Quest::query()
            ->with([
                'client:id,name,email,slug',
                'freelancer:id,name,email,slug',
                'questCategory:id,name,parent_id',
                'questCategory.parent:id,name',
                'stateModel:id,name',
            ]);

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('reference_code', 'like', '%'.$q.'%');
            });
        }

        $status = (string) $request->input('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $quests = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Operations/Quests/Index', [
            'quests' => $quests,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'status_options' => collect(QuestStatus::cases())->map(fn (QuestStatus $s) => [
                'value' => $s->value,
                'label' => str_replace('_', ' ', ucfirst($s->value)),
            ])->values()->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Quest::query()
            ->with(['client:id,email', 'freelancer:id,email', 'questCategory:id,name']);

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('reference_code', 'like', '%'.$q.'%');
            });
        }

        $status = (string) $request->input('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $header = [
            'id',
            'reference_code',
            'title',
            'status',
            'escrow_status',
            'client_email',
            'freelancer_email',
            'category',
            'created_at',
        ];

        return AdminCsv::download('operations-quests-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($quests) use ($out): void {
                foreach ($quests as $quest) {
                    fputcsv($out, [
                        $quest->id,
                        $quest->reference_code,
                        $quest->title,
                        $quest->status instanceof QuestStatus ? $quest->status->value : (string) $quest->status,
                        $quest->escrow_status,
                        $quest->client?->email,
                        $quest->freelancer?->email,
                        $quest->questCategory?->name,
                        $quest->created_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
