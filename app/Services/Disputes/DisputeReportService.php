<?php

namespace App\Services\Disputes;

use App\Models\DisputeEvent;
use App\Models\QuestDispute;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DisputeReportService
{
    public function generate(QuestDispute $dispute, User $actor): string
    {
        $dispute->load([
            'quest.client:id,name,email',
            'quest.freelancer:id,name,email',
            'openedBy:id,name,email',
            'assignedStaff:id,name,email',
            'superAdminDecidedBy:id,name,email',
            'events.actor:id,name,email',
            'assessments.staff:id,name,email',
            'mediationSessions',
            'precedents',
        ]);

        $html = view('pdf.dispute-report', [
            'dispute' => $dispute,
            'events' => $dispute->events,
            'assessments' => $dispute->assessments,
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');
        $path = 'dispute-reports/'.$dispute->uuid.'/'.now()->format('Y-m-d-His').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        $dispute->forceFill([
            'report_path' => $path,
            'report_generated_at' => now(),
        ])->save();

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => 'admin.report_generated',
            'properties' => ['path' => $path],
            'created_at' => now(),
        ]);

        return $path;
    }

    public function download(QuestDispute $dispute): Response
    {
        if (! $dispute->report_path || ! Storage::disk('local')->exists($dispute->report_path)) {
            abort(404, __('Report not found.'));
        }

        return response(Storage::disk('local')->get($dispute->report_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$dispute->displayReference().'-report.pdf"',
        ]);
    }

    public function sealAndArchive(QuestDispute $dispute, User $actor): QuestDispute
    {
        $dispute->forceFill([
            'sealed_at' => now(),
            'finalized_at' => $dispute->finalized_at ?? now(),
            'management_status' => \App\Enums\QuestDisputeManagementStatus::Finalized,
        ])->save();

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => 'admin.sealed_archived',
            'properties' => [],
            'created_at' => now(),
        ]);

        return $dispute->fresh();
    }
}
