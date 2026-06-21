<?php

namespace App\Services\Admin\ContractManagement;

use App\Enums\ContractStatus;
use App\Models\QuestContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContractManagementExportService
{
    public function __construct(
        private readonly ContractManagementDashboardService $dashboard,
    ) {}

    public function exportPdf(Request $request): Response
    {
        $rows = $this->dashboard->exportRows($request);
        $overview = $this->dashboard->overviewStats();
        $filename = 'contract-management-'.now()->format('Y-m-d-His').'.pdf';

        return Pdf::loadView('pdf.contract-management-report', [
            'overview' => $overview,
            'rows' => $rows,
            'filters' => $request->only(['q', 'status', 'risk_level', 'quick_view']),
            'generated_at' => now()->timezone('Africa/Lagos'),
            'generated_by' => $request->user()?->name ?? 'Staff',
        ])->setPaper('a4', 'landscape')->download($filename);
    }
}
