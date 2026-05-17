<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportAdminQuestSupportNotesRequest;
use App\Models\Quest;
use App\Services\AdminActivityLogger;
use App\Support\AdminCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminQuestsController extends Controller
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

        return Inertia::render('Admin/Quests/Index', [
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

        return AdminCsv::download('quests-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
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

    public function import(ImportAdminQuestSupportNotesRequest $request, AdminActivityLogger $logger): RedirectResponse
    {
        $actor = $request->user();
        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => __('Could not read the uploaded file.')]);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => __('Could not read the uploaded file.')]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return back()->withErrors(['file' => __('CSV is empty.')]);
        }

        $norm = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $refIdx = array_search('reference_code', $norm, true);
        $noteIdx = array_search('support_note', $norm, true);
        if ($refIdx === false || $noteIdx === false) {
            fclose($handle);

            return back()->withErrors(['file' => __('CSV must include columns: reference_code, support_note')]);
        }

        $processed = 0;
        $skipped = 0;
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (! isset($row[$refIdx], $row[$noteIdx])) {
                $skipped++;

                continue;
            }

            $code = trim((string) $row[$refIdx]);
            $note = trim((string) $row[$noteIdx]);
            if ($code === '' || $note === '') {
                $skipped++;

                continue;
            }

            $quest = Quest::query()->where('reference_code', $code)->first();
            if ($quest === null) {
                $skipped++;

                continue;
            }

            $logger->log(
                actor: $actor,
                action: 'admin.quest_support_note_import',
                subjectType: Quest::class,
                subjectId: $quest->id,
                properties: [
                    'reference_code' => $code,
                    'support_note' => mb_substr($note, 0, 2000),
                    'csv_row' => $rowNum,
                ],
                request: $request,
            );
            $processed++;
            if ($processed >= 500) {
                break;
            }
        }

        fclose($handle);

        return back()->with('success', __('Logged :n support note(s); skipped :s row(s).', ['n' => $processed, 's' => $skipped]));
    }
}
