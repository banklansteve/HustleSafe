<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HR Payroll History</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 20px 0 8px; }
        .meta { color: #475569; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
        th { background: #e2e8f0; font-size: 11px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>Payroll History Report</h1>
    <p class="meta">
        Staff: {{ $staff->name }} ({{ $staff->email }})<br>
        Base Salary: NGN {{ number_format((float) ($profile->base_salary ?? 0), 2) }}<br>
        Frequency: {{ $profile->payment_frequency ?? 'monthly' }}<br>
        Generated: {{ $generatedAt->toDateTimeString() }}
    </p>

    <h2>Payroll Adjustments</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Reason</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            @forelse($adjustments as $row)
                <tr>
                    <td>{{ optional($row->effective_date)->toDateString() }}</td>
                    <td>{{ strtoupper($row->type) }}</td>
                    <td>NGN {{ number_format((float) $row->amount, 2) }}</td>
                    <td>{{ $row->reason }}</td>
                    <td>{{ $row->reference ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No payroll adjustments available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Payslip History</h2>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Gross</th>
                <th>Bonus</th>
                <th>Deduction</th>
                <th>Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payslips as $row)
                <tr>
                    <td>{{ sprintf('%04d-%02d', $row->year, $row->month) }}</td>
                    <td>NGN {{ number_format((float) $row->gross_pay, 2) }}</td>
                    <td>NGN {{ number_format((float) $row->bonuses, 2) }}</td>
                    <td>NGN {{ number_format((float) $row->deductions, 2) }}</td>
                    <td>NGN {{ number_format((float) $row->net_pay, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No payslip records available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
