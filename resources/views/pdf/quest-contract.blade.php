<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->reference_code }} — Contract</title>
    <style>
        @page { margin: 48px 52px 64px 52px; }
        body { font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #0f172a; line-height: 1.55; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 22px; }
        .brand { font-size: 16pt; font-weight: 700; letter-spacing: -0.02em; }
        .meta { margin-top: 6px; font-size: 9pt; color: #475569; }
        h2 { font-size: 11pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: #334155; margin: 22px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        h3 { font-size: 10pt; font-weight: 700; margin: 12px 0 4px; color: #1e293b; }
        p { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; width: 32%; }
        ul { margin: 6px 0 10px; padding-left: 18px; }
        li { margin-bottom: 4px; }
        .signatures { margin-top: 24px; page-break-inside: avoid; }
        .sig-block { border: 1px solid #cbd5e1; padding: 12px; margin-bottom: 10px; border-radius: 4px; }
        .sig-name { font-weight: 700; font-size: 11pt; }
        .sig-meta { font-size: 9pt; color: #475569; margin-top: 4px; }
        .amendment { border-left: 3px solid #6366f1; padding-left: 12px; margin: 10px 0 14px; }
        .footer { position: fixed; bottom: -36px; left: 0; right: 0; text-align: center; font-size: 8pt; color: #64748b; }
    </style>
</head>
<body>
@php
    $parties = $contract->parties_snapshot ?? [];
    $quest = $terms['quest'] ?? $contract->quest_snapshot ?? [];
    $financial = $terms['financial'] ?? $contract->financial_snapshot ?? [];
    $timeline = $terms['timeline'] ?? $contract->timeline_snapshot ?? [];
    $revision = $terms['revision_policy'] ?? $contract->revision_policy_snapshot ?? [];
    $platform = $contract->platform_terms_snapshot ?? [];
    $signatures = $contract->signatures_snapshot ?? [];
    $generated = $contract->generated_at?->timezone('Africa/Lagos')->format('j M Y · g:i A T');
@endphp

<div class="header">
    <div class="brand">{{ $platformName }}</div>
    <div class="meta">
        <strong>Contract reference:</strong> {{ $contract->reference_code }} &nbsp;·&nbsp;
        <strong>Generated:</strong> {{ $generated ?? '—' }} &nbsp;·&nbsp;
        <strong>Status:</strong> {{ $contract->status->label() }}
    </div>
</div>

<h2>Parties</h2>
<table>
    <tr><th>Client</th><td>{{ $parties['client']['full_name'] ?? '—' }} (@{{ $parties['client']['username'] ?? '—' }}) · ID {{ $parties['client']['user_id'] ?? '—' }}</td></tr>
    <tr><th>Freelancer</th><td>{{ $parties['freelancer']['full_name'] ?? '—' }} (@{{ $parties['freelancer']['username'] ?? '—' }}) · ID {{ $parties['freelancer']['user_id'] ?? '—' }}</td></tr>
</table>

<h2>Quest details</h2>
<table>
    <tr><th>Quest title</th><td>{{ $quest['title'] ?? '—' }}</td></tr>
    <tr><th>Quest reference</th><td>{{ $quest['reference_code'] ?? '—' }}</td></tr>
    <tr><th>Category</th><td>{{ $quest['category'] ?? '—' }}</td></tr>
    <tr><th>Scope</th><td>{{ $quest['scope_description'] ?? '—' }}</td></tr>
</table>

@if($contract->deliverables->isNotEmpty())
<h3>Deliverables</h3>
<ol>
    @foreach($contract->deliverables as $deliverable)
        <li>
            <strong>{{ $deliverable->title }}</strong>
            @if($deliverable->description)
                — {{ $deliverable->description }}
            @endif
        </li>
    @endforeach
</ol>
@endif

<h2>Financial terms</h2>
<table>
    <tr><th>Total contract value</th><td>{{ $financial['total_label'] ?? '—' }}</td></tr>
    <tr><th>Platform service fee</th><td>{{ $financial['platform_fee_label'] ?? '—' }} ({{ $financial['platform_fee_percent'] ?? '—' }}%)</td></tr>
    <tr><th>Freelancer net payout</th><td>{{ $financial['freelancer_net_label'] ?? '—' }}</td></tr>
    @if($contract->escrow_funding_reference)
    <tr><th>Escrow funding reference</th><td>{{ $contract->escrow_funding_reference }} @if($contract->escrow_funded_at) · {{ $contract->escrow_funded_at->timezone('Africa/Lagos')->format('j M Y · g:i A T') }} @endif</td></tr>
    @endif
</table>

@if($contract->milestones->isNotEmpty())
<h3>Milestones</h3>
<table>
    <tr><th>Name</th><th>Deliverable</th><th>Value</th><th>Deadline</th></tr>
    @foreach($contract->milestones as $milestone)
    <tr>
        <td>{{ $milestone->name }}</td>
        <td>{{ $milestone->deliverable_reference ?? '—' }}</td>
        <td>₦{{ number_format($milestone->value_minor / 100, 0) }}</td>
        <td>{{ $milestone->deadline_date?->format('j M Y') ?? '—' }}</td>
    </tr>
    @endforeach
</table>
@endif

<h2>Timeline</h2>
<table>
    <tr><th>Contract start</th><td>{{ $contract->contract_start_date?->format('j M Y') ?? 'Pending escrow funding' }}</td></tr>
    <tr><th>Agreed delivery date</th><td>{{ $timeline['agreed_delivery_label'] ?? '—' }}</td></tr>
    <tr><th>Auto-release policy</th><td>{{ $timeline['auto_release_plain_english'] ?? '—' }}</td></tr>
</table>

<h2>Revision policy</h2>
<table>
    <tr><th>Included revisions</th><td>{{ $revision['revisions_included'] ?? $contract->revisions_included ?? 0 }}</td></tr>
    <tr><th>Definition</th><td>{{ $revision['revision_definition'] ?? '—' }}</td></tr>
</table>

@if($contract->amendments->where('status', 'accepted')->isNotEmpty())
<h2>Amendment history</h2>
@foreach($contract->amendments->where('status', 'accepted') as $amendment)
<div class="amendment">
    <p><strong>Amendment #{{ $amendment->amendment_number }}</strong> — {{ $amendment->amendment_type->label() }}</p>
    <p>{{ $amendment->description }}</p>
    @if($amendment->original_value || $amendment->new_value)
    <p><em>Original:</em> {{ $amendment->original_value ?? '—' }} &nbsp;·&nbsp; <em>New:</em> {{ $amendment->new_value ?? '—' }}</p>
    @endif
    @if($amendment->responded_at)
    <p class="sig-meta">Accepted {{ $amendment->responded_at->timezone('Africa/Lagos')->format('j M Y · g:i A T') }}</p>
    @endif
</div>
@endforeach
@endif

<h2>Platform terms</h2>
<p>Full Terms of Service: {{ $platform['terms_url'] ?? route('legal.terms', absolute: true) }}</p>
<ul>
    @foreach(($platform['clauses'] ?? []) as $clause)
        <li>{{ $clause }}</li>
    @endforeach
</ul>

<div class="signatures">
    <h2>Signatures</h2>
    <div class="sig-block">
        <div class="sig-name">{{ $signatures['client']['name'] ?? 'Client' }}</div>
        <div class="sig-meta">{{ $signatures['client']['action'] ?? '' }}</div>
        <div class="sig-meta">{{ isset($signatures['client']['confirmed_at']) ? \Carbon\Carbon::parse($signatures['client']['confirmed_at'])->timezone('Africa/Lagos')->format('j M Y · g:i A T') : '—' }}</div>
    </div>
    <div class="sig-block">
        <div class="sig-name">{{ $signatures['freelancer']['name'] ?? 'Freelancer' }}</div>
        <div class="sig-meta">{{ $signatures['freelancer']['action'] ?? '' }}</div>
        <div class="sig-meta">{{ isset($signatures['freelancer']['confirmed_at']) ? \Carbon\Carbon::parse($signatures['freelancer']['confirmed_at'])->timezone('Africa/Lagos')->format('j M Y · g:i A T') : '—' }}</div>
    </div>
    <div class="sig-block">
        <div class="sig-name">{{ $signatures['platform']['name'] ?? $platformName }}</div>
        <div class="sig-meta">{{ $signatures['platform']['action'] ?? 'Facilitating party' }}</div>
        <div class="sig-meta">{{ isset($signatures['platform']['confirmed_at']) ? \Carbon\Carbon::parse($signatures['platform']['confirmed_at'])->timezone('Africa/Lagos')->format('j M Y · g:i A T') : '—' }}</div>
    </div>
</div>

<script type="text/php">
    if (isset($pdf)) {
        $text = "{{ $platformName }} — Confidential Contract Document — {{ $contract->reference_code }} — Page {PAGE_NUM} of {PAGE_COUNT}";
        $font = $fontMetrics->getFont("DejaVu Sans");
        $size = 8;
        $width = $fontMetrics->getTextWidth($text, $font, $size);
        $x = ($pdf->get_width() - $width) / 2;
        $pdf->page_text($x, $pdf->get_height() - 28, $text, $font, $size, [0.39, 0.45, 0.55]);
    }
</script>
</body>
</html>
