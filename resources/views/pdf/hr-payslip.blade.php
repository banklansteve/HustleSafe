<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Staff Payslip</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; margin: 24px; }
        .header { border-bottom: 2px solid #0ea5e9; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { width: 100%; margin-bottom: 8px; }
        .brand td { vertical-align: middle; }
        .brand-logo { width: 220px; max-height: 52px; object-fit: contain; }
        .title { font-size: 24px; font-weight: 700; margin: 0; letter-spacing: 0.4px; }
        .muted { color: #475569; font-size: 11px; }
        .meta-grid { width: 100%; margin-top: 12px; }
        .meta-grid td { vertical-align: top; padding: 2px 0; }
        .chip { display: inline-block; background: #e0f2fe; color: #0c4a6e; border-radius: 999px; padding: 4px 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .row { width: 100%; margin-top: 16px; }
        .col { width: 48%; display: inline-block; vertical-align: top; }
        .col + .col { margin-left: 3%; }
        .panel { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; }
        .panel h3 { margin: 0 0 8px; font-size: 13px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 6px 4px; text-align: left; font-size: 11px; }
        th { color: #334155; text-transform: uppercase; font-size: 10px; }
        .right { text-align: right; }
        .summary { margin-top: 16px; border: 1px solid #0ea5e9; border-radius: 10px; padding: 12px; background: #f0f9ff; }
        .summary td { padding: 4px 0; font-size: 12px; }
        .net { font-size: 18px; font-weight: 700; color: #0c4a6e; }
    </style>
</head>
<body>
    @php
        $companyName = config('app.name', 'HustleSafe');
        $logoCandidates = [
            public_path('images/logo/hustlesafe_payslip_logo.png'),
            public_path('images/logo/v7b_banner_light.png'),
        ];
        $logoDataUri = null;
        foreach ($logoCandidates as $logoPath) {
            if (! file_exists($logoPath)) {
                continue;
            }
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
            break;
        }
        $roleLabel = ucfirst((string) ($staff->role?->slug ?? 'operations'));
        $basicSalary = (float) $payslip->gross_pay - (float) $payslip->bonuses;
    @endphp
    <div class="header">
        <table class="brand">
            <tr>
                <td>
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="{{ $companyName }} logo" class="brand-logo">
                    @endif
                </td>
                <td class="right">
                    <strong>{{ $companyName }}</strong>
                </td>
            </tr>
        </table>
        <p class="title">Payslip</p>
        <span class="chip">{{ $monthLabel }}</span>
        <table class="meta-grid">
            <tr>
                <td><strong>Full Name:</strong> {{ $staff->name }}</td>
                <td><strong>Staff ID:</strong> {{ $staffCode }}</td>
            </tr>
            <tr>
                <td><strong>Role:</strong> {{ $roleLabel }}</td>
                <td><strong>Month:</strong> {{ $monthLabel }}</td>
            </tr>
            <tr>
                <td><strong>Email:</strong> {{ $staff->email }}</td>
                <td><strong>Currency:</strong> NGN</td>
            </tr>
        </table>
    </div>

    <div class="row">
        <div class="col">
            <div class="panel">
                <h3>Earnings</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="right">NGN {{ number_format($basicSalary, 2) }}</td>
                        </tr>
                        @foreach($allowances as $item)
                            <tr>
                                <td>{{ $item->reference ?: 'Allowance' }}</td>
                                <td class="right">NGN {{ number_format((float) $item->amount, 2) }}</td>
                            </tr>
                        @endforeach
                        @if($allowances->isEmpty())
                            <tr>
                                <td colspan="2">No allowances recorded.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col">
            <div class="panel">
                <h3>Deductions</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deductions as $item)
                            @php
                                $deductionAmount = (float) $item->amount;
                                if (($item->deduction_mode ?? 'flat') === 'percentage') {
                                    $percent = ((float) ($item->deduction_percentage ?? 0)) / 100;
                                    $baseAmount = match ($item->deduction_basis ?? 'basic_salary') {
                                        'total_pay' => (float) $payslip->gross_pay,
                                        'custom_amount' => (float) ($item->deduction_custom_base_amount ?? 0),
                                        default => $basicSalary,
                                    };
                                    $deductionAmount = round($baseAmount * $percent, 2);
                                }
                            @endphp
                            <tr>
                                <td>{{ $item->reference ?: 'Deduction' }}</td>
                                <td class="right">
                                    @if($item->deduction_mode === 'percentage')
                                        NGN {{ number_format($deductionAmount, 2) }} ({{ number_format((float) ($item->deduction_percentage ?? 0), 2) }}%)
                                    @else
                                        NGN {{ number_format((float) $item->amount, 2) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if($deductions->isEmpty())
                            <tr>
                                <td colspan="2">No deductions recorded.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td><strong>Gross Pay</strong></td>
                <td class="right"><strong>NGN {{ number_format((float) $payslip->gross_pay, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Total Allowances</td>
                <td class="right">NGN {{ number_format((float) $payslip->bonuses, 2) }}</td>
            </tr>
            <tr>
                <td>Total Deductions</td>
                <td class="right">NGN {{ number_format((float) $payslip->deductions, 2) }}</td>
            </tr>
            <tr>
                <td class="net">Net Monthly Pay</td>
                <td class="right net">NGN {{ number_format((float) $payslip->net_pay, 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
