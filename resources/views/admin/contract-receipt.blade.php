<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Contract receipt — {{ $quest->title }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; color: #0f172a; }
        h1 { font-size: 1.25rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        td, th { text-align: left; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; }
        th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
    </style>
</head>
<body>
    <h1>HustleSafe contract summary</h1>
    <p><strong>Quest:</strong> {{ $quest->title }} ({{ $quest->reference_code }})</p>
    <p><strong>Client:</strong> {{ $quest->client?->name }} · <strong>Freelancer:</strong> {{ $quest->freelancer?->name ?? '—' }}</p>
    <p><strong>Issued:</strong> {{ $issued_at }} (WAT)</p>
    <table>
        <tr><th>Grand total</th><td>{{ $grand }}</td></tr>
        <tr><th>VAT</th><td>{{ $vat }}</td></tr>
        <tr><th>Platform fee</th><td>{{ $platform_fee }}</td></tr>
        <tr><th>Discount</th><td>{{ $discount }}</td></tr>
        <tr><th>Escrow status</th><td>{{ $quest->escrow_status }}</td></tr>
    </table>
    <p style="margin-top:2rem;font-size:0.85rem;color:#64748b;">For accounting and VAT records. Print or save as PDF from your browser.</p>
</body>
</html>
