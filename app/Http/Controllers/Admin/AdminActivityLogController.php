<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Support\AdminCsv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));
        $actionFilter = (string) $request->input('action', '');

        $logs = AdminActivityLog::query()
            ->with('actor:id,name,email')
            ->when($actionFilter === 'quest_completion', function ($query): void {
                $query->where(function ($sub): void {
                    $sub->where('action', 'like', 'quest.completion.%')
                        ->orWhere('action', 'quest.escrow.released_admin_override');
                });
            })
            ->when($actionFilter !== '' && $actionFilter !== 'quest_completion', fn ($query) => $query->where('action', 'like', '%'.$actionFilter.'%'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Activity/Index', [
            'logs' => $logs,
            'filters' => [
                'per_page' => $perPage,
                'action' => $actionFilter,
            ],
        ]);
    }

    public function export(): StreamedResponse
    {
        $header = ['id', 'actor_email', 'action', 'subject_type', 'subject_id', 'created_at'];

        return AdminCsv::download('admin-activity-'.now()->format('Y-m-d-His').'.csv', $header, function ($out): void {
            AdminActivityLog::query()
                ->with('actor:id,email')
                ->orderByDesc('id')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $log) {
                        fputcsv($out, [
                            $log->id,
                            $log->actor?->email,
                            $log->action,
                            $log->subject_type,
                            $log->subject_id,
                            $log->created_at?->toIso8601String(),
                        ]);
                    }
                });
        });
    }
}
