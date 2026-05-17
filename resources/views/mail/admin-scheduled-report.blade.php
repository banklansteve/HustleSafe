<p>Hello,</p>

<p>Your scheduled HustleSafe report <strong>{{ $report->name }}</strong> is attached as a PDF.</p>

<p>
    Report type: {{ str_replace('_', ' ', $report->report_type) }}<br>
    Schedule: {{ $report->schedule_frequency ?? 'manual' }}<br>
    Generated: {{ now()->format('d-m-Y h:ia') }}
</p>

<p>The download link in the admin console will expire after 7 days.</p>
