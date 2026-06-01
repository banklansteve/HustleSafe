<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialEscrowRecord;
use App\Models\FinancialReconciliationException;
use App\Models\User;
use App\Services\Finance\FinancialAuditDashboardService;
use App\Services\Finance\FinancialAuditExceptionService;
use App\Services\Finance\FinancialAuditReportService;
use App\Services\Finance\FinancialReconciliationReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFinancialAuditController extends Controller
{
    public function __construct(
        private readonly FinancialAuditDashboardService $dashboard,
        private readonly FinancialAuditExceptionService $exceptions,
        private readonly FinancialAuditReportService $reports,
        private readonly FinancialReconciliationReportService $reconciliation,
    ) {}

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
    }

    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/FinancialAudit/Overview', [
            'overview' => $this->dashboard->overview($request),
            'categories' => $this->dashboard->categoryFilterOptions(),
        ]);
    }

    public function dashboardApi(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        return response()->json($this->dashboard->overview($request));
    }

    public function reconciliationIndex(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/FinancialAudit/Reconciliation', [
            'report' => $this->reconciliation->report($request),
        ]);
    }

    public function reconciliationExport(Request $request): StreamedResponse
    {
        $this->authorizeSuperAdmin($request);

        return $this->reports->exportReconciliationCsv($request);
    }

    public function escrowLedger(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/FinancialAudit/EscrowLedger', [
            'listing' => $this->dashboard->escrowLedgerListing($request),
            'statuses' => ['held', 'released', 'refunded', 'partially_released', 'disputed'],
            'categories' => $this->dashboard->categoryFilterOptions(),
        ]);
    }

    public function escrowRecordShow(Request $request, FinancialEscrowRecord $record): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/FinancialAudit/EscrowRecordShow', [
            'detail' => $this->dashboard->escrowRecordDetail($record),
        ]);
    }

    public function escrowLedgerExport(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorizeSuperAdmin($request);

        return $this->reports->exportEscrowLedger($request);
    }

    public function exceptionsIndex(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        $superAdmins = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/FinancialAudit/Exceptions', [
            'listing' => $this->exceptions->listing(),
            'super_admins' => $superAdmins,
        ]);
    }

    public function assignException(Request $request, FinancialReconciliationException $exception): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::query()->findOrFail($data['assigned_to_user_id']);
        $this->exceptions->assign($exception, $assignee);

        return back()->with('success', __('Exception assigned.'));
    }

    public function noteException(Request $request, FinancialReconciliationException $exception): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $this->exceptions->addNotes($exception, $data['notes']);

        return back()->with('success', __('Investigation notes saved.'));
    }

    public function resolveException(Request $request, FinancialReconciliationException $exception): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'resolution_description' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $this->exceptions->resolve($exception, $request->user(), $data['resolution_description']);

        return back()->with('success', __('Exception resolved.'));
    }

    public function vatReport(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/FinancialAudit/VatReport', [
            'report' => $this->reports->vatReport($request),
        ]);
    }

    public function platformFeeReport(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/FinancialAudit/PlatformFeeReport', [
            'report' => $this->reports->platformFeeReport($request),
        ]);
    }

    public function vatReportExport(Request $request): StreamedResponse
    {
        $this->authorizeSuperAdmin($request);

        return $this->reports->exportVatReportCsv($request);
    }

    public function platformFeeReportExport(Request $request): StreamedResponse
    {
        $this->authorizeSuperAdmin($request);

        return $this->reports->exportPlatformFeeReportCsv($request);
    }

    /** @deprecated Redirects to VAT report */
    public function reportsIndex(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        return redirect()->route('admin.financial-audit.reports.vat', $request->query());
    }

    public function recordVatRemittance(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'quarter_label' => ['required', 'string', 'max:32'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'remittance_reference' => ['required', 'string', 'max:120'],
            'remitted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reports->recordVatRemittance($request->user(), $data);

        return back()->with('success', __('VAT remittance recorded.'));
    }
}
