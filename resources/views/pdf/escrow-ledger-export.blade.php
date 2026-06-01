<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Escrow Ledger Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        .meta { margin-bottom: 12px; color: #444; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body>
    <h1>HustleSafe Escrow Ledger</h1>
    <div class="meta">
        Period: {{ $from }} to {{ $to }} · Generated {{ $generated_at->format('d M Y H:i') }} · {{ count($rows) }} records
    </div>
    <table>
        <thead>
            <tr>
                <th>Escrow ref</th><th>Contract</th><th>Quest</th><th>Client</th><th>Freelancer</th>
                <th>Gross</th><th>Fee</th><th>VAT</th><th>Net</th><th>Status</th><th>Funded</th><th>Paystack ref</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['escrow_reference'] }}</td>
                    <td>{{ $row['contract_reference'] }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row['quest_title'], 40) }}</td>
                    <td>{{ $row['client_name'] }}</td>
                    <td>{{ $row['freelancer_name'] }}</td>
                    <td>{{ $row['gross_display'] }}</td>
                    <td>{{ $row['platform_fee_display'] }}</td>
                    <td>{{ $row['vat_display'] }}</td>
                    <td>{{ $row['freelancer_net_display'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['funded_at'] ? \Carbon\Carbon::parse($row['funded_at'])->format('Y-m-d') : '—' }}</td>
                    <td>{{ $row['paystack_reference'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
