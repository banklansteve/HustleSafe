<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Revenue Monitor Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 12px; color: #444; }
        .grid { width: 100%; margin-bottom: 14px; }
        .grid td { vertical-align: top; padding: 6px; }
        .box { border: 1px solid #ddd; padding: 8px; }
        .label { font-size: 8px; text-transform: uppercase; color: #666; }
        .value { font-size: 13px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        table.data th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>HustleSafe Revenue Monitor</h1>
    <div class="meta">
        Period: {{ $period['label'] }} ({{ $period['from'] }} to {{ $period['to'] }}) · Generated {{ $generated_at->format('d M Y H:i') }}
    </div>

    <table class="grid">
        <tr>
            <td class="box" width="50%">
                <div class="label">Total gross revenue</div>
                <div class="value">{{ $overview['total_gross_display'] }}</div>
            </td>
            <td class="box" width="50%">
                <div class="label">Net revenue</div>
                <div class="value">{{ $overview['total_net_display'] }}</div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            @foreach($overview['streams'] as $stream)
                <td class="box">
                    <div class="label">{{ $stream['label'] }}</div>
                    <div class="value">{{ $stream['amount_display'] }} ({{ $stream['percent'] }}%)</div>
                </td>
            @endforeach
        </tr>
    </table>

    <div class="meta">
        Daily average: {{ $trend_insights['daily_average_display'] ?? '—' }} ·
        Active premium: {{ $overview['active_premium_users'] }} ·
        Active boosts: {{ $overview['active_boosted_quests'] }}
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>Type</th><th>Txn ID</th><th>Party</th><th>Date</th><th>Gross</th><th>Net</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $row)
                <tr>
                    <td>{{ $row['revenue_type_label'] }}</td>
                    <td>{{ $row['transaction_id'] }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row['party_label'] ?? '', 36) }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['amount_display'] }}</td>
                    <td>{{ $row['net_display'] }}</td>
                    <td>{{ $row['status_label'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
