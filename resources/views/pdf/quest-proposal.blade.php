<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Proposal') }} — {{ $quest->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; line-height: 1.45; margin: 28px 36px; }
        h1 { font-size: 18px; margin: 0 0 8px; color: #0b3d2e; }
        h2 { font-size: 12px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.06em; color: #334155; }
        .muted { color: #64748b; font-size: 10px; }
        .box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; margin-top: 8px; background: #f8fafc; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
        .right { text-align: right; }
        .prose p { margin: 0 0 6px; }
        .prose ul { margin: 0; padding-left: 16px; }
    </style>
</head>
<body>
    <h1>{{ __('Proposal summary') }}</h1>
    <p class="muted">{{ config('app.name') }} · {{ $quest->reference_code ?? $quest->uuid }} · {{ now()->timezone('Africa/Lagos')->format('M j, Y H:i') }}</p>

    <h2>{{ __('Quest') }}</h2>
    <div class="box">
        <strong>{{ $quest->title }}</strong><br>
        <span class="muted">{{ $quest->questCategory?->parent?->name }} · {{ $quest->questCategory?->name }}</span><br>
        {{ $quest->stateModel?->name }}@if($quest->localGovernment) — {{ $quest->localGovernment->name }}@endif
        @if($quest->city) · {{ $quest->city }}@endif
    </div>

    <h2>{{ __('Freelancer') }}</h2>
    <div class="box">
        {{ $offer->freelancer?->name }}<br>
        <span class="muted">{{ $offer->freelancer?->headline }}</span>
    </div>

    <h2>{{ __('Executive pitch') }}</h2>
    <div class="box prose">{!! $pitchHtml !!}</div>

    <h2>{{ __('Scope & approach') }}</h2>
    <div class="box prose">{!! $scopeHtml !!}</div>

    @if($warrantyHtml)
        <h2>{{ __('Warranty / assurance') }}</h2>
        <div class="box prose">{!! $warrantyHtml !!}</div>
    @endif

    <h2>{{ __('Timeline') }}</h2>
    <div class="box">
        <div>{{ __('Planned start') }}: <strong>{{ $offer->planned_start_date?->format('M j, Y') ?? '—' }}</strong></div>
        <div>{{ __('Planned finish') }}: <strong>{{ $offer->planned_finish_date?->format('M j, Y') ?? '—' }}</strong></div>
        @if($offer->estimated_duration_days)
            <div class="muted">{{ __('Estimated duration') }}: {{ $offer->estimated_duration_days }} {{ __('days') }}</div>
        @endif
        @if($offer->progress_report_frequency)
            <div class="muted">{{ __('Progress reports') }}: {{ str_replace('_', ' ', $offer->progress_report_frequency) }}</div>
        @endif
        @if($offer->corrections_included)
            <div class="muted">{{ __('Corrections / redo rounds included') }}: {{ (int) ($offer->corrections_rounds ?? 0) }}</div>
        @else
            <div class="muted">{{ __('Corrections / redo') }}: {{ __('Not included in quote') }}</div>
        @endif
    </div>

    <h2>{{ __('Materials & parts') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                <th class="right">{{ __('Qty') }}</th>
                <th class="right">{{ __('Unit (₦)') }}</th>
                <th class="right">{{ __('Line (₦)') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($offer->materials ?? [] as $m)
                @php($line = (int) ($m['line_total_minor'] ?? $m['cost_minor'] ?? 0))
                @php($unit = (int) ($m['unit_price_minor'] ?? 0))
                <tr>
                    <td>{{ $m['label'] ?? '' }}</td>
                    <td class="right">{{ $m['quantity'] ?? '—' }}</td>
                    <td class="right">@if($unit > 0)₦{{ number_format($unit / 100, 0, '.', ',') }}@else—@endif</td>
                    <td class="right">₦{{ number_format($line / 100, 0, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php($p = $offer->pricing_snapshot ?? [])
    <h2>{{ __('Pricing (NGN)') }}</h2>
    <table>
        <tbody>
            <tr><td>{{ __('Professional fee') }}</td><td class="right">₦{{ number_format((int)($p['professional_fee_minor'] ?? 0) / 100, 0, '.', ',') }}</td></tr>
            <tr><td>{{ __('Materials subtotal') }}</td><td class="right">₦{{ number_format((int)($p['materials_total_minor'] ?? 0) / 100, 0, '.', ',') }}</td></tr>
            @if((int)($p['travel_cost_minor'] ?? 0) > 0)
                <tr><td>{{ __('Travel') }}</td><td class="right">₦{{ number_format((int)($p['travel_cost_minor'] ?? 0) / 100, 0, '.', ',') }}</td></tr>
            @endif
            <tr>
                <td>
                    {{ __('VAT') }}
                    @if(array_key_exists('vat_applies', $p) && !($p['vat_applies'] ?? true))
                        <span class="muted">({{ __('not applied') }})</span>
                    @elseif(isset($p['vat_percent']))
                        <span class="muted">({{ rtrim(rtrim(number_format((float) $p['vat_percent'], 2, '.', ''), '0'), '.') }}%)</span>
                    @endif
                </td>
                <td class="right">₦{{ number_format((int)($p['vat_minor'] ?? 0) / 100, 0, '.', ',') }}</td>
            </tr>
            <tr><td>{{ __('Withholding tax') }}</td><td class="right">₦{{ number_format((int)($p['withholding_tax_minor'] ?? 0) / 100, 0, '.', ',') }}</td></tr>
            <tr><td>{{ __('Stamp duty') }}</td><td class="right">₦{{ number_format((int)($p['stamp_duty_minor'] ?? 0) / 100, 0, '.', ',') }}</td></tr>
            <tr><td>{{ __('Platform / processing') }}</td><td class="right">₦{{ number_format((int)($p['platform_fee_minor'] ?? 0) / 100, 0, '.', ',') }}</td></tr>
            <tr><td>{{ __('Discount') }}</td><td class="right">−₦{{ number_format((int)($p['discount_minor'] ?? 0) / 100, 0, '.', ',') }}</td></tr>
            <tr><th>{{ __('Grand total') }}</th><th class="right">₦{{ number_format((int)($p['grand_total_minor'] ?? 0) / 100, 0, '.', ',') }}</th></tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top:24px;">{{ __('This PDF is an export for convenience. The authoritative record is your signed agreement inside HustleSafe.') }}</p>
</body>
</html>
