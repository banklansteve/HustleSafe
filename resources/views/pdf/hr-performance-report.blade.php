<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HR Performance Report</title>
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
    <h1>Monthly Performance Report</h1>
    <p class="meta">
        Staff: {{ $staff->name }} ({{ $staff->email }})<br>
        Generated: {{ $generatedAt->toDateTimeString() }}
    </p>

    <h2>Monthly Scores (Last 12 Months)</h2>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Score</th>
                <th>Volume</th>
                <th>Resolution</th>
                <th>Speed</th>
                <th>Overridden</th>
            </tr>
        </thead>
        <tbody>
            @forelse($scores as $row)
                <tr>
                    <td>{{ sprintf('%04d-%02d', $row->year, $row->month) }}</td>
                    <td>{{ number_format((float) ($row->overridden ? ($row->overridden_score ?? $row->score) : $row->score), 2) }}</td>
                    <td>{{ (int) $row->volume_points }}</td>
                    <td>{{ (int) $row->resolution_points }}</td>
                    <td>{{ (int) $row->speed_points }}</td>
                    <td>{{ $row->overridden ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No performance records available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
