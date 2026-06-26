<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial Health Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 12px; color: #444; }
        .grid { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .grid td { vertical-align: top; padding: 6px; border: 1px solid #ddd; }
        .label { font-size: 8px; text-transform: uppercase; color: #666; }
        .value { font-size: 12px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        table.data th { background: #f3f4f6; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; padding: 6px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <h1>HustleSafe Financial Health</h1>
    <div class="meta">
        Period: {{ $period_label }} ({{ $date_from }} to {{ $date_to }}) · Generated {{ $generated_at->timezone('Africa/Lagos')->format('d M Y H:i') }} WAT
    </div>

    <table class="grid">
        <tr>
            <td width="25%"><div class="label">Escrow funded</div><div class="value">{{ $kpis['escrow_funded']['total_display'] }}</div></td>
            <td width="25%"><div class="label">Platform fees</div><div class="value">{{ $kpis['platform_fee']['total_display'] }}</div></td>
            <td width="25%"><div class="label">VAT collected</div><div class="value">{{ $kpis['vat_collected']['total_display'] }}</div></td>
            <td width="25%"><div class="label">Net revenue</div><div class="value">{{ $kpis['net_revenue']['total_display'] }}</div></td>
        </tr>
    </table>

    @if(!empty($alerts['items']))
        <div class="meta"><strong>Alerts</strong></div>
        @foreach($alerts['items'] as $alert)
            <div class="alert">{{ $alert['message'] }}</div>
        @endforeach
    @endif

    <table class="grid">
        <tr>
            <td width="33%"><div class="label">Escrow held (system)</div><div class="value">{{ $reconciliation['escrow']['system_held_display'] }}</div></td>
            <td width="33%"><div class="label">Ledger liability</div><div class="value">{{ $reconciliation['escrow']['bank_balance_display'] }}</div></td>
            <td width="33%"><div class="label">VAT outstanding</div><div class="value">{{ $reconciliation['vat']['outstanding_display'] }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Transaction ID</th><th>Type</th><th>Amount</th><th>Status</th><th>Due</th><th>Direction</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['type_label'] }}</td>
                    <td>{{ $row['amount_display'] }}</td>
                    <td>{{ $row['status_label'] }}</td>
                    <td>{{ $row['due_date'] ?? '—' }}</td>
                    <td>{{ ucfirst($row['direction']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
