<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .brand { font-weight: 800; letter-spacing: .12em; color: #0f766e; text-transform: uppercase; }
        h1 { margin: 6px 0 2px; font-size: 22px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th { text-align: left; background: #0f766e; color: white; padding: 8px; font-size: 10px; text-transform: uppercase; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px; }
    </style>
</head>
<body>
    <div class="brand">HustleSafe</div>
    <h1>{{ $result['name'] ?? $export->report_name }}</h1>
    <p class="muted">
        Date range: {{ $result['date_range']['from'] ?? '—' }} to {{ $result['date_range']['to'] ?? '—' }}
        · Generated {{ now()->format('d-m-Y h:ia') }}
    </p>

    <table>
        <thead>
            <tr>
                @foreach (($result['columns'] ?? []) as $column)
                    <th>{{ str_replace('_', ' ', $column) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach (($result['rows'] ?? []) as $row)
                <tr>
                    @foreach (($result['columns'] ?? []) as $column)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
