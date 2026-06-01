<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $type === 'vat_audit' ? 'VAT Audit Report' : 'Platform Fee Audit Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 16px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        .summary { margin-top: 16px; }
    </style>
</head>
<body>
    <h1>{{ $type === 'vat_audit' ? 'VAT Audit Report' : 'Platform Fee Audit Report' }}</h1>
    <div class="meta">
        Period: {{ $start->format('d M Y') }} — {{ $end->format('d M Y') }}<br>
        Generated: {{ now()->format('d M Y H:i') }} by {{ $generated_by }}<br>
        HustleSafe Financial Audit System
    </div>

    <table>
        <thead>
            @if($type === 'vat_audit')
                <tr>
                    <th>Date</th><th>Contract</th><th>Gross</th><th>Platform fee</th><th>VAT %</th><th>VAT</th><th>Cumulative VAT</th>
                </tr>
            @else
                <tr>
                    <th>Date</th><th>Month</th><th>Contract</th><th>Category</th><th>Fee</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach($rows as $row)
                @if($type === 'vat_audit')
                    <tr>
                        <td>{{ $row['transaction_date'] }}</td>
                        <td>{{ $row['contract_reference'] }}</td>
                        <td>{{ $row['gross_display'] }}</td>
                        <td>{{ $row['platform_fee_display'] }}</td>
                        <td>{{ $row['vat_rate'] }}%</td>
                        <td>{{ $row['vat_display'] }}</td>
                        <td>{{ $row['cumulative_vat_display'] }}</td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $row['transaction_date'] }}</td>
                        <td>{{ $row['month'] }}</td>
                        <td>{{ $row['contract_reference'] }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['fee_display'] }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <strong>Summary:</strong>
        {{ $summary['transaction_count'] ?? 0 }} transactions.
        @if($type === 'vat_audit')
            Total VAT: {{ \App\Support\NgnMoney::format((int) ($summary['total_vat_minor'] ?? 0)) }}
        @else
            Total fees: {{ \App\Support\NgnMoney::format((int) ($summary['total_fee_minor'] ?? 0)) }}
        @endif
    </div>
</body>
</html>
