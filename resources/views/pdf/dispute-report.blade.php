<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Dispute Report — {{ $dispute->displayReference() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; line-height: 1.45; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 11px; margin: 14px 0 6px; text-transform: uppercase; letter-spacing: 0.04em; }
        .meta { margin-bottom: 12px; color: #444; }
        .seal { margin-top: 20px; padding: 8px; border: 2px solid #111; text-align: center; font-weight: bold; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: bold; }
        .muted { color: #555; }
        .block { margin-bottom: 8px; }
    </style>
</head>
<body>
    <h1>Dispute Resolution Report</h1>
    <div class="meta">
        Reference: <strong>{{ $dispute->displayReference() }}</strong><br>
        Quest: {{ $dispute->quest?->title ?? '—' }} ({{ $dispute->quest?->reference_code ?? '—' }})<br>
        Generated: {{ now()->timezone('Africa/Lagos')->format('d M Y H:i') }} WAT<br>
        HustleSafe Dispute Resolution — Confidential
    </div>

    <h2>Parties</h2>
    <table>
        <tr>
            <th>Role</th>
            <th>Name</th>
            <th>Email</th>
        </tr>
        <tr>
            <td>Client</td>
            <td>{{ $dispute->quest?->client?->name ?? '—' }}</td>
            <td>{{ $dispute->quest?->client?->email ?? '—' }}</td>
        </tr>
        <tr>
            <td>Freelancer</td>
            <td>{{ $dispute->quest?->freelancer?->name ?? '—' }}</td>
            <td>{{ $dispute->quest?->freelancer?->email ?? '—' }}</td>
        </tr>
        <tr>
            <td>Filed by</td>
            <td colspan="2">{{ $dispute->openedBy?->name ?? '—' }}</td>
        </tr>
    </table>

    <h2>Case summary</h2>
    <div class="block">
        <strong>Reason:</strong> {{ $dispute->reason }}<br>
        <strong>Status:</strong> {{ $dispute->management_status?->label() ?? $dispute->management_status }}<br>
        <strong>Disputed amount:</strong> ₦{{ number_format(($dispute->disputed_amount_minor ?? 0) / 100, 2) }}<br>
        <strong>Opened:</strong> {{ $dispute->created_at?->timezone('Africa/Lagos')->format('d M Y H:i') ?? '—' }}<br>
        @if($dispute->assignedStaff)
            <strong>Investigator:</strong> {{ $dispute->assignedStaff->name }}<br>
        @endif
        @if($dispute->outcome_action)
            <strong>Outcome action:</strong> {{ str_replace('_', ' ', $dispute->outcome_action) }}<br>
        @endif
        @if($dispute->final_client_share_percent !== null)
            <strong>Final split:</strong> Client {{ $dispute->final_client_share_percent }}% / Freelancer {{ 100 - $dispute->final_client_share_percent }}%<br>
        @endif
    </div>
    <div class="block">
        <strong>Opening summary</strong><br>
        {{ $dispute->opening_summary }}
    </div>

    @if($assessments->isNotEmpty())
        <h2>Staff assessments</h2>
        @foreach($assessments as $assessment)
            <div class="block">
                <strong>{{ $assessment->staff?->name ?? 'Staff' }}</strong>
                — {{ $assessment->status }}
                @if($assessment->submitted_at)
                    ({{ $assessment->submitted_at->timezone('Africa/Lagos')->format('d M Y') }})
                @endif
                <br>
                Recommendation: {{ $assessment->recommendation?->label() ?? $assessment->recommendation ?? '—' }}<br>
                @if($assessment->reasoning)
                    <span class="muted">{{ $assessment->reasoning }}</span>
                @endif
            </div>
        @endforeach
    @endif

    @if($dispute->super_admin_decision_notes)
        <h2>Super Admin decision</h2>
        <div class="block">{{ $dispute->super_admin_decision_notes }}</div>
        @if($dispute->superAdminDecidedBy)
            <p class="muted">Decided by {{ $dispute->superAdminDecidedBy->name }}
                @if($dispute->super_admin_decided_at)
                    on {{ $dispute->super_admin_decided_at->timezone('Africa/Lagos')->format('d M Y H:i') }}
                @endif
            </p>
        @endif
    @endif

  @if($dispute->mediationSessions?->isNotEmpty())
        <h2>Mediation sessions</h2>
        <table>
            <thead>
                <tr>
                    <th>Scheduled</th>
                    <th>Status</th>
                    <th>Instructions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dispute->mediationSessions as $session)
                    <tr>
                        <td>{{ $session->scheduled_at?->timezone('Africa/Lagos')->format('d M Y H:i') ?? '—' }}</td>
                        <td>{{ $session->status }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($session->instructions ?? '', 200) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Audit trail</h2>
    <table>
        <thead>
            <tr>
                <th>When</th>
                <th>Actor</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $event)
                <tr>
                    <td>{{ $event->created_at?->timezone('Africa/Lagos')->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ $event->actor?->name ?? 'System' }}</td>
                    <td>{{ app(\App\Services\Disputes\DisputeEventLabelService::class)->label($event->action) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($dispute->sealed_at)
        <div class="seal">
            SEALED &amp; ARCHIVED — {{ $dispute->sealed_at->timezone('Africa/Lagos')->format('d M Y H:i') }} WAT
        </div>
    @endif
</body>
</html>
