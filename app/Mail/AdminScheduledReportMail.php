<?php

namespace App\Mail;

use App\Models\AdminReportExport;
use App\Models\AdminSavedReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AdminScheduledReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AdminSavedReport $report,
        public AdminReportExport $export
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HustleSafe report: '.$this->report->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-scheduled-report',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if (! $this->export->path) {
            return [];
        }

        return [
            Attachment::fromPath(Storage::disk($this->export->disk)->path($this->export->path))
                ->as(str($this->report->name)->slug().'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
