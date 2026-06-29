<?php

namespace App\Http\Controllers\Operations;

use App\Enums\QuestDisputeManagementStatus;
use App\Http\Controllers\Controller;
use App\Models\QuestDispute;
use App\Services\Disputes\DisputeManagementPermissionService;
use App\Services\Operations\StaffDisputeManagementService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsDisputesController extends Controller
{
    public function __construct(
        private readonly StaffDisputeManagementService $service,
        private readonly DisputeManagementPermissionService $permissions,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Disputes/Index', [
            'queue_summary' => $this->service->queueSummary(request()->user()),
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        $paginator = $this->service->listing($request, staffScoped: true);

        return response()->json([
            'items' => $paginator->items(),
            'meta' => ['total' => $paginator->total()],
            'queue_summary' => $this->service->queueSummary($request->user()),
        ]);
    }

    public function detail(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->permissions->assertCanView($request->user(), $dispute);

        return response()->json($this->service->detail($dispute, $request->user()));
    }

    public function claim(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->claim($dispute, $request->user(), $request);

        return response()->json(['message' => __('Dispute claimed.')]);
    }

    public function acknowledge(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->acknowledge($dispute, $request->user(), $request);

        return response()->json(['message' => __('Dispute acknowledged. Parties have been notified.')]);
    }

    public function checklist(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['completed' => ['required', 'array'], 'completed.*' => ['string', 'max:64']]);
        $workflow = $this->service->updateChecklist($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Checklist updated.'), 'workflow' => $workflow]);
    }

    public function evidenceReviewed(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);
        $this->service->markEvidenceReviewed($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Evidence marked as reviewed.')]);
    }

    public function awaitingInfo(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'audience' => ['nullable', 'in:both,client,freelancer'],
        ]);
        $this->service->markAwaitingInfo($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Awaiting-info notice sent.')]);
    }

    public function requestReassignment(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $this->service->requestReassignment($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Reassignment request sent to Super Admin.')]);
    }

    public function internalNote(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:4', 'max:5000']]);
        $this->service->internalNote($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Internal note saved.')]);
    }

    public function notice(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'audience' => ['nullable', 'in:both,client,freelancer'],
        ]);

        $this->service->postNotice($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Notice posted to parties.')]);
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

        return response()->json(['message' => __('Message sent.')]);
    }

    public function requestEvidence(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'audience' => ['nullable', 'in:both,client,freelancer'],
        ]);

        $this->service->requestEvidence($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Evidence request sent.')]);
    }

    public function assessment(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'quality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'investigation_checklist' => ['nullable', 'array'],
            'investigation_checklist.*' => ['string', 'max:64'],
            'violation_status' => ['nullable', 'string', 'max:48'],
            'key_findings' => ['nullable', 'array'],
            'key_findings.*' => ['string', 'max:500'],
            'recommendation' => ['nullable', 'string'],
            'recommended_client_share_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'recommended_sanction' => ['nullable', 'string', 'max:48'],
            'alternate_recommendations' => ['nullable', 'array'],
            'alternate_recommendations.*' => ['string', 'max:48'],
            'reasoning' => ['nullable', 'string', 'max:8000'],
            'time_spent_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'submit' => ['nullable', 'boolean'],
        ]);

        $assessment = $this->service->saveAssessment($dispute, $request->user(), $data, $request);

        return response()->json([
            'message' => $assessment->isSubmitted() ? __('Assessment submitted.') : __('Assessment saved.'),
            'assessment' => $assessment,
        ]);
    }

    public function readyForDecision(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->markReadyForDecision($dispute, $request->user(), $request);

        return response()->json(['message' => __('Marked ready for Super Admin review.')]);
    }

    public function approveMutualAgreement(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->approveMutualAgreement($dispute, $request->user(), $request);

        return response()->json(['message' => __('Mutual agreement approved and funds are being processed.')]);
    }

    public function respondGuidance(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:8', 'max:5000'],
        ]);

        $this->service->respondToStaffGuidance($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Response sent to Super Admin.')]);
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
        $query = QuestDispute::query()
            ->where('assigned_staff_id', $request->user()->id)
            ->with(['quest:id,reference_code', 'openedBy:id,email']);

        $header = ['reference', 'contract', 'management_status', 'severity', 'value_minor', 'created_at'];

        return AdminCsv::download('operations-disputes-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($rows) use ($out): void {
                foreach ($rows as $d) {
                    fputcsv($out, [
                        $d->displayReference(),
                        $d->quest?->reference_code,
                        $d->management_status?->value ?? (string) $d->management_status,
                        $d->severity,
                        $d->disputed_amount_minor,
                        $d->created_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
