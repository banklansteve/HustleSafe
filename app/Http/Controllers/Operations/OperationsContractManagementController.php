<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractManagement\ContractManagementFlagRequest;
use App\Http\Requests\ContractManagement\ContractManagementNoteRequest;
use App\Http\Requests\ContractManagement\ContractManagementQualityReviewRequest;
use App\Http\Requests\ContractManagement\ContractManagementReasonRequest;
use App\Models\ContractPatrolFlag;
use App\Models\ContractSavedFilter;
use App\Models\QuestContract;
use App\Services\Admin\ContractManagement\ContractManagementActionService;
use App\Services\Admin\ContractManagement\ContractManagementDashboardService;
use App\Services\Admin\ContractManagement\ContractManagementDetailService;
use App\Services\Admin\ContractManagement\ContractManagementExportService;
use App\Services\Admin\ContractManagement\ContractManagementSavedFilterService;
use App\Services\Admin\ContractManagement\ContractManagementSettingsService;
use App\Services\Admin\ContractManagement\ContractPatrolAnomalyService;
use App\Services\Admin\ContractManagement\ContractQualityAuditService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsContractManagementController extends Controller
{
    protected bool $isSuperAdmin = false;

    protected string $routePrefix = 'operations';

    protected bool $useAdminShell = false;

    public function __construct(
        protected readonly ContractManagementDashboardService $dashboard,
        protected readonly ContractManagementDetailService $detail,
        protected readonly ContractManagementActionService $actions,
        protected readonly ContractPatrolAnomalyService $patrol,
        protected readonly ContractManagementSavedFilterService $savedFilters,
        protected readonly ContractManagementExportService $export,
        protected readonly ContractQualityAuditService $qualityAudit,
        protected readonly ContractManagementSettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('ContractManagement/Index', [
            ...$this->dashboard->indexPayload($request, $this->isSuperAdmin),
            'route_prefix' => $this->routePrefix,
            'use_admin_shell' => $this->useAdminShell,
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->listing($request));
    }

    public function alerts(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->dashboard->alerts(
                limit: min(100, $request->integer('limit', 30)),
                type: $request->query('type') ? (string) $request->query('type') : null,
            ),
        ]);
    }

    public function disputes(Request $request): JsonResponse
    {
        return response()->json($this->dashboard->disputeListing($request));
    }

    public function patrolFlags(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->patrol->openFlags(
                limit: min(100, $request->integer('limit', 50)),
                severity: $request->query('severity') ? (string) $request->query('severity') : null,
            ),
        ]);
    }

    public function qualityAuditSample(Request $request): JsonResponse
    {
        $sampleSize = min(200, max(5, $request->integer('sample_size', (int) config('contract_management.quality_audit.default_sample_size', 50))));

        return response()->json($this->qualityAudit->randomSample($sampleSize, $request->query('status') ? (string) $request->query('status') : null));
    }

    public function savedFilters(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->savedFilters->listForUser($request->user()),
        ]);
    }

    public function storeSavedFilter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['required', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $filter = $this->savedFilters->save(
            $request->user(),
            (string) $data['name'],
            $data['filters'],
            (bool) ($data['is_default'] ?? false),
        );

        return response()->json(['filter' => $filter, 'message' => __('Filter saved.')]);
    }

    public function destroySavedFilter(Request $request, ContractSavedFilter $filter): JsonResponse
    {
        $this->savedFilters->delete($request->user(), $filter);

        return response()->json(['message' => __('Filter deleted.')]);
    }

    public function acknowledgePatrolFlag(Request $request, ContractPatrolFlag $flag): JsonResponse
    {
        $this->patrol->acknowledge($flag, $request->user());

        return response()->json(['message' => __('Patrol flag acknowledged.')]);
    }

    public function dismissPatrolFlag(ContractManagementReasonRequest $request, ContractPatrolFlag $flag): JsonResponse
    {
        $this->patrol->dismiss($flag, $request->user(), $request->validated('reason'));

        return response()->json(['message' => __('Patrol flag dismissed.')]);
    }

    public function detail(QuestContract $contract): JsonResponse
    {
        $this->authorize('view', $contract);

        return response()->json($this->detail->build($contract, $this->isSuperAdmin));
    }

    public function assign(QuestContract $contract, Request $request): JsonResponse
    {
        $this->authorize('view', $contract);
        $this->actions->assign($contract, $request->user(), $request);

        return response()->json(['message' => __('Contract assigned to you.')]);
    }

    public function note(ContractManagementNoteRequest $request, QuestContract $contract): JsonResponse
    {
        $this->authorize('view', $contract);
        $this->actions->addNote($contract, $request->user(), $request->validated('body'), $request);

        return response()->json(['message' => __('Note added.')]);
    }

    public function flag(ContractManagementFlagRequest $request, QuestContract $contract): JsonResponse
    {
        $this->authorize('view', $contract);
        $this->actions->flagForReview($contract, $request->user(), $request->validated('reason'), $request);

        return response()->json(['message' => __('Contract flagged for review.')]);
    }

    public function qualityReview(ContractManagementQualityReviewRequest $request, QuestContract $contract): JsonResponse
    {
        $this->authorize('view', $contract);
        $data = $request->validated();
        $this->actions->qualityReview($contract, $request->user(), (int) $data['rating'], $data['notes'], $request);

        return response()->json(['message' => __('Quality review saved.')]);
    }

    public function acknowledgeAlert(Request $request, QuestContract $contract): JsonResponse
    {
        $this->authorize('view', $contract);
        $request->validate(['alert_type' => ['required', 'string', 'max:80']]);
        $this->actions->acknowledgeAlert($contract, $request->user(), (string) $request->input('alert_type'), $request);

        return response()->json(['message' => __('Alert acknowledged.')]);
    }

    public function holdEscrow(ContractManagementReasonRequest $request, QuestContract $contract): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->authorize('view', $contract);
        $this->actions->holdEscrow($contract, $request->user(), $request->validated('reason'), $request);

        return response()->json(['message' => __('Escrow hold applied.')]);
    }

    public function terminate(ContractManagementReasonRequest $request, QuestContract $contract): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->authorize('view', $contract);
        $this->actions->terminate($contract, $request->user(), $request->validated('reason'), $request);

        return response()->json(['message' => __('Contract terminated.')]);
    }

    public function forceApproveDelivery(ContractManagementReasonRequest $request, QuestContract $contract): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->authorize('view', $contract);
        $this->actions->forceApproveDelivery($contract, $request->user(), $request->validated('reason'), $request);

        return response()->json(['message' => __('Delivery approved by staff.')]);
    }

    public function forceRejectDelivery(ContractManagementReasonRequest $request, QuestContract $contract): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->authorize('view', $contract);
        $this->actions->forceRejectDelivery($contract, $request->user(), $request->validated('reason'), $request);

        return response()->json(['message' => __('Delivery sent back for revision.')]);
    }

    public function releasePayment(ContractManagementReasonRequest $request, QuestContract $contract): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->authorize('view', $contract);
        $this->actions->releasePayment($contract, $request->user(), $request->validated('reason'), $request);

        return response()->json(['message' => __('Payment released.')]);
    }

    public function partialRelease(Request $request, QuestContract $contract): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->authorize('view', $contract);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'amount_minor' => ['required', 'integer', 'min:100'],
        ]);
        $this->actions->releasePayment($contract, $request->user(), $data['reason'], $request, (int) $data['amount_minor']);

        return response()->json(['message' => __('Partial release processed.')]);
    }

    public function liftEscrowHold(ContractManagementReasonRequest $request, QuestContract $contract): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $this->authorize('view', $contract);
        $this->actions->liftEscrowHold($contract, $request->user(), $request->validated('reason'), $request);

        return response()->json(['message' => __('Escrow hold lifted.')]);
    }

    public function bulkRelease(Request $request): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $data = $request->validate([
            'reference_codes' => ['required', 'array', 'min:1', 'max:50'],
            'reference_codes.*' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $result = $this->actions->bulkReleasePayments($data['reference_codes'], $request->user(), $data['reason'], $request);

        return response()->json([
            'message' => __(':count contract(s) processed.', ['count' => $result['processed']]),
            ...$result,
        ]);
    }

    public function bulkHoldEscrow(Request $request): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $data = $request->validate([
            'reference_codes' => ['required', 'array', 'min:1', 'max:50'],
            'reference_codes.*' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $result = $this->actions->bulkHoldEscrow($data['reference_codes'], $request->user(), $data['reason'], $request);

        return response()->json([
            'message' => __(':count contract(s) held.', ['count' => $result['processed']]),
            ...$result,
        ]);
    }

    public function reconcileEscrow(Request $request): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $result = $this->actions->reconcileEscrow($request->user(), $request);

        return response()->json($result);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($this->isSuperAdmin, 403);
        $keys = array_keys(config('contract_management.settings', []));
        $rules = [];
        foreach ($keys as $key) {
            $meta = config("contract_management.settings.{$key}", []);
            $rules[$key] = ['sometimes', 'integer', 'min:'.($meta['min'] ?? 0), 'max:'.($meta['max'] ?? 9999)];
        }
        $data = $request->validate($rules);
        $this->settings->update($data, $request->user(), $request);

        return response()->json([
            'message' => __('Contract settings updated.'),
            'settings' => $this->settings->payload(),
        ]);
    }

    public function exportPdf(Request $request)
    {
        return $this->export->exportPdf($request);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->dashboard->exportRows($request);

        return AdminCsv::download('contracts-'.now()->format('Y-m-d-His').'.csv', [
            'Reference',
            'Quest',
            'Client',
            'Freelancer',
            'Amount (NGN)',
            'Status',
            'Payment',
            'Delivery',
            'Risk',
            'Due date',
            'Flagged',
        ], function ($out) use ($rows): void {
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['reference_code'],
                    $row['quest_title'],
                    $row['client']['name'] ?? '',
                    $row['freelancer']['name'] ?? '',
                    number_format(($row['amount_minor'] ?? 0) / 100, 2, '.', ''),
                    $row['status_label'],
                    $row['payment_status'],
                    $row['delivery_status_label'],
                    $row['risk_level'],
                    $row['due_label'] ?? '',
                    ($row['flagged_for_review'] ?? false) ? 'yes' : 'no',
                ]);
            }
        });
    }
}
