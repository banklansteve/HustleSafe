<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\StaffPayrollAdjustment;
use App\Models\StaffPayrollProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OperationsHrExportController extends Controller
{
    public function payrollHistory(): Response
    {
        $staff = request()->user();
        $profile = StaffPayrollProfile::query()->where('staff_user_id', $staff->id)->first();
        $adjustments = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->latest('effective_date')
            ->limit(24)
            ->get();
        $payslips = DB::table('staff_payslips')
            ->where('staff_user_id', $staff->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(24)
            ->get();

        $html = view('pdf.hr-payroll-history', [
            'staff' => $staff,
            'profile' => $profile,
            'adjustments' => $adjustments,
            'payslips' => $payslips,
            'generatedAt' => now(),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->download('my-payroll-history-'.$staff->id.'.pdf');
    }

    public function performanceReport(): Response
    {
        $staff = request()->user();
        $scores = DB::table('staff_performance_scores')
            ->where('staff_user_id', $staff->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get();

        $html = view('pdf.hr-performance-report', [
            'staff' => $staff,
            'scores' => $scores,
            'generatedAt' => now(),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->download('my-performance-report-'.$staff->id.'.pdf');
    }
}
