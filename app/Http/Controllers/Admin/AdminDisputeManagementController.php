<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuestDispute;
use App\Services\Admin\SuperAdminDisputeManagementService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDisputeManagementController extends Controller
{
    public function __construct(private readonly SuperAdminDisputeManagementService $service) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Disputes/Index', [
            'summary' => $this->service->summary(),
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        $paginator = $this->service->listing($request);

        return response()->json([
            'items' => $paginator->items(),
            'meta' => ['total' => $paginator->total()],
            'summary' => $this->service->summary(),
        ]);
    }

    public function detail(QuestDispute $dispute, Request $request): JsonResponse
    {
        return response()->json($this->service->detail($dispute, $request->user()));
    }

    public function decision(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'outcome_action' => ['nullable', 'string', 'in:standard_payout,force_revision,extend_deadline,terminate_contract,refund_cancel,mediation'],
            'outcome' => ['nullable', 'string', 'max:120'],
            'client_share_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'decision_notes' => ['nullable', 'string', 'max:8000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'extended_deadline_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'meeting_url' => ['nullable', 'url', 'max:500'],
            'favoured_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sanctions' => ['nullable', 'array'],
            'sanctions.warn_freelancer' => ['nullable', 'boolean'],
            'sanctions.warn_client' => ['nullable', 'boolean'],
            'sanctions.type' => ['nullable', 'string', 'max:48'],
            'sanctions.target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sanctions.suspend_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sanctions.category_id' => ['nullable', 'integer'],
        ]);

        $outcomeAction = (string) ($data['outcome_action'] ?? 'standard_payout');
        if ($outcomeAction === 'standard_payout') {
            $request->validate([
                'outcome' => ['required', 'string', 'max:120'],
                'client_share_percent' => ['required', 'integer', 'min:0', 'max:100'],
                'decision_notes' => ['required', 'string', 'min:20', 'max:8000'],
            ]);
        } elseif (in_array($outcomeAction, ['force_revision', 'terminate_contract', 'refund_cancel'], true)) {
            $request->validate([
                'decision_notes' => ['required', 'string', 'min:20', 'max:8000'],
            ]);
        }

        $this->service->executeDecision($dispute, $request->user(), $data, $request);
        $dispute->refresh();

        $message = $dispute->management_status === \App\Enums\QuestDisputeManagementStatus::AwaitingEnforcement
            ? __('Decision issued. Parties have the enforcement window to appeal before funds are distributed.')
            : __('Decision executed and parties notified.');

        return response()->json(['message' => $message]);
    }

    public function reassign(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $this->service->reassign($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Dispute reassigned.')]);
    }

    public function requestReview(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->requestMoreReview($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Sent back for staff review.')]);
    }

    public function finalize(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->finalize($dispute, $request->user(), $request);

        return response()->json(['message' => __('Dispute finalized.')]);
    }

    public function acknowledgePartyResolution(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->acknowledgePartyResolution($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Party resolution acknowledged.')]);
    }

    public function appealReview(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->createAppealReview($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Appeal review opened.')]);
    }

    public function resolveAppeal(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'upheld_original' => ['required', 'boolean'],
            'client_share_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'review_outcome_notes' => ['required', 'string', 'min:20', 'max:8000'],
        ]);

        $this->service->resolveAppeal($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Appeal resolved. Decision is now final and binding.')]);
    }

    public function approveAssessment(Request $request, QuestDispute $dispute): JsonResponse
    {
        $payload = $this->service->approveAssessment($dispute, $request->user(), $request);

        return response()->json(['message' => __('Staff assessment loaded for approval.'), 'decision' => $payload]);
    }

    public function superAdminNote(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:4', 'max:5000']]);
        $this->service->superAdminNote($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Private note saved.')]);
    }

    public function requestClarification(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->requestStaffClarification($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Clarification requested from staff.')]);
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

    public function messageParty(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'party' => ['required', 'in:client,freelancer'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'channel' => ['nullable', 'in:both,email,in_app'],
        ]);
        $this->service->messageParty($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Message sent to party.')]);
    }

    public function hold(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        $this->service->holdDispute($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Dispute placed on hold.')]);
    }

    public function releaseHold(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->releaseHold($dispute, $request->user(), $request);

        return response()->json(['message' => __('Hold released.')]);
    }

    public function rateAssessment(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'assessment_id' => ['required', 'integer', 'exists:dispute_assessments,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->service->rateStaffAssessment($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Staff assessment rated.')]);
    }

    public function scheduleMediation(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'meeting_url' => ['nullable', 'url', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->service->scheduleMediation($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Mediation session scheduled and parties notified.')]);
    }

    public function flagChargeback(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->service->flagChargebackRisk($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Chargeback risk flagged on this dispute.')]);
    }

    public function patternInvestigation(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->service->openPatternInvestigation($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Pattern investigation opened.')]);
    }

    public function createPrecedent(Request $request, QuestDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:200'],
            'summary' => ['required', 'string', 'min:20', 'max:8000'],
            'category' => ['nullable', 'string', 'max:64'],
        ]);
        $this->service->createPrecedent($dispute, $request->user(), $data, $request);

        return response()->json(['message' => __('Precedent recorded.')]);
    }

    public function generateReport(Request $request, QuestDispute $dispute): JsonResponse
    {
        $path = $this->service->generateReport($dispute, $request->user(), $request);

        return response()->json([
            'message' => __('Dispute report generated.'),
            'download_url' => route('admin.api.disputes.download_report', $dispute),
            'path' => $path,
        ]);
    }

    public function downloadReport(QuestDispute $dispute, Request $request)
    {
        if (! app(\App\Services\Disputes\DisputeManagementPermissionService::class)->isSuperAdmin($request->user())) {
            abort(403);
        }

        return app(\App\Services\Disputes\DisputeReportService::class)->download($dispute);
    }

    public function sealArchive(Request $request, QuestDispute $dispute): JsonResponse
    {
        $this->service->sealAndArchive($dispute, $request->user(), $request);

        return response()->json(['message' => __('Dispute sealed and archived.')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = QuestDispute::query()->with(['quest:id,reference_code', 'openedBy:id,email', 'assignedStaff:id,name']);

        $header = ['reference', 'contract', 'management_status', 'severity', 'assigned_staff', 'value_minor', 'created_at'];

        return AdminCsv::download('admin-disputes-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($rows) use ($out): void {
                foreach ($rows as $d) {
                    fputcsv($out, [
                        $d->displayReference(),
                        $d->quest?->reference_code,
                        $d->management_status?->value ?? (string) $d->management_status,
                        $d->severity,
                        $d->assignedStaff?->name,
                        $d->disputed_amount_minor,
                        $d->created_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
