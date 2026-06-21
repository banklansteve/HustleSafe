<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Contract Management Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 12px; color: #444; }
        .kpis { margin-bottom: 16px; }
        .kpis span { display: inline-block; margin-right: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Contract Management Report</h1>
    <div class="meta">
        Generated: {{ $generated_at->format('d M Y H:i') }} (WAT) by {{ $generated_by }}<br>
        HustleSafe Contract Management Console
    </div>

    <div class="kpis">
        <span><strong>Active:</strong> {{ $overview['active_contracts'] ?? 0 }}</span>
        <span><strong>Awaiting approval:</strong> {{ $overview['awaiting_approval'] ?? 0 }}</span>
        <span><strong>In dispute:</strong> {{ $overview['in_dispute'] ?? 0 }}</span>
        <span><strong>Overdue:</strong> {{ $overview['overdue'] ?? 0 }}</span>
        <span><strong>Escrow held:</strong> {{ $overview['escrow_held_formatted'] ?? '—' }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Quest</th>
                <th>Client</th>
                <th>Freelancer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Delivery</th>
                <th>Risk</th>
                <th>Due</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['reference_code'] }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row['quest_title'] ?? '', 40) }}</td>
                    <td>{{ $row['client']['name'] ?? '' }}</td>
                    <td>{{ $row['freelancer']['name'] ?? '' }}</td>
                    <td>{{ $row['amount_formatted'] ?? '' }}</td>
                    <td>{{ $row['status_label'] ?? '' }}</td>
                    <td>{{ $row['delivery_status_label'] ?? '' }}</td>
                    <td>{{ $row['risk_level'] ?? '' }}</td>
                    <td>{{ $row['due_label'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
