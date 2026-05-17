<?php

namespace App\Jobs;

use App\Models\AdminReportExport;
use App\Services\Admin\AdvancedReportEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateAdminReportExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AdminReportExport $export
    ) {}

    public function handle(AdvancedReportEngine $engine): void
    {
        $this->export->forceFill(['status' => 'processing'])->save();

        try {
            $result = $engine->run($this->export->payload ?? []);
            $path = $this->pathFor($this->export);
            $content = match ($this->export->format) {
                'pdf' => Pdf::loadView('pdf.admin-report', ['export' => $this->export, 'result' => $result])->output(),
                'xlsx' => $this->excelContent($result),
                default => $this->csvContent($result),
            };

            Storage::disk($this->export->disk)->put($path, $content);

            $this->export->forceFill([
                'status' => 'completed',
                'path' => $path,
                'completed_at' => now(),
                'expires_at' => $this->export->expires_at ?? now()->addDays(7),
            ])->save();
        } catch (Throwable $e) {
            $this->export->forceFill([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    private function pathFor(AdminReportExport $export): string
    {
        return 'admin-report-exports/'.$export->id.'-'.str($export->report_name)->slug().'.'.$this->extension($export->format);
    }

    private function extension(string $format): string
    {
        return $format === 'xlsx' ? 'xls' : $format;
    }

    private function csvContent(array $result): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $result['columns'] ?? []);
        foreach ($result['rows'] ?? [] as $row) {
            fputcsv($handle, array_map(fn ($column) => $row[$column] ?? '', $result['columns'] ?? []));
        }
        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    private function excelContent(array $result): string
    {
        $columns = $result['columns'] ?? [];
        $rows = $result['rows'] ?? [];
        $html = '<table><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>'.e(str_replace('_', ' ', $column)).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $html .= '<td>'.e((string) ($row[$column] ?? '')).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }
}
