<?php

namespace App\Http\Controllers\Operations;

use App\Enums\QuestDisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\QuestDispute;
use App\Services\Operations\StaffDisputeManagementService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsDisputesController extends Controller
{
    public function __construct(private readonly StaffDisputeManagementService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Disputes/Index', [
            'queues' => $this->service->queues(),
            'status_options' => collect(QuestDisputeStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => str_replace('_', ' ', ucfirst($s->value)),
            ])->values()->all(),
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        $paginator = $this->service->listing($request);

        return response()->json([
            'items' => $paginator->items(),
            'meta' => ['total' => $paginator->total()],
        ]);
    }

    public function detail(QuestDispute $dispute): JsonResponse
    {
        return response()->json($this->service->detail($dispute));
    }

    public function claim(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->claim($dispute, $request->user(), $request);

        return response()->json(['message' => 'Dispute claimed.']);
    }

    public function internalNote(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:4', 'max:5000']]);
        $this->service->internalNote($dispute, $request->user(), $data, $request);

        return response()->json(['message' => 'Internal note saved.']);
    }

    public function notice(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'audience' => ['nullable', 'in:both,client,freelancer'],
        ]);

        $this->service->postNotice($dispute, $request->user(), $data, $request);

        return response()->json(['message' => 'Notice posted to parties.']);
    }

    public function contact(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'party' => ['required', 'in:client,freelancer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'channel' => ['nullable', 'in:both,email,in_app'],
        ]);

        $this->service->contactParty($dispute, $request->user(), $data, $request);

        return response()->json(['message' => 'Message sent.']);
    }

    public function requestEvidence(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'audience' => ['nullable', 'in:both,client,freelancer'],
        ]);

        $this->service->requestEvidence($dispute, $request->user(), $data, $request);

        return response()->json(['message' => 'Evidence request sent.']);
    }

    public function tier(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'tier' => ['required', 'integer', 'in:1,2,3'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->setTier($dispute, $request->user(), $data, $request);

        return response()->json(['message' => 'Mediation tier updated.']);
    }

    public function ruling(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', 'string', 'max:120'],
            'summary' => ['required', 'string', 'min:20', 'max:5000'],
            'client_share_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'favoured_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->service->issueRuling($dispute, $request->user(), $data, $request);

        return response()->json(['message' => 'Ruling issued and parties notified.']);
    }

    public function export(Request $request): StreamedResponse
    {
        $status = trim((string) $request->input('status', ''));
        $query = QuestDispute::query()->with(['quest:id,reference_code', 'openedBy:id,email']);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $header = ['id', 'uuid', 'quest_reference', 'status', 'phase', 'opened_by_email', 'disputed_amount_minor', 'created_at', 'resolved_at'];

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
