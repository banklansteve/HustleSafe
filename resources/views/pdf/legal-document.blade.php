<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] ?? 'Legal document' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #0f172a; font-size: 11pt; line-height: 1.55; margin: 28px; }
        h1 { font-size: 20pt; margin: 0 0 6px; color: #0f766e; }
        .meta { font-size: 9pt; color: #64748b; margin-bottom: 20px; }
        .summary { background: #f0fdfa; border: 1px solid #99f6e4; padding: 14px 16px; margin-bottom: 24px; border-radius: 8px; }
        .summary h2 { font-size: 10pt; text-transform: uppercase; letter-spacing: 0.08em; color: #0f766e; margin: 0 0 8px; }
        .summary ul { margin: 0; padding-left: 18px; }
        .summary li { margin-bottom: 6px; }
        section { margin-bottom: 22px; page-break-inside: avoid; }
        h2 { font-size: 13pt; margin: 0 0 10px; color: #0f172a; }
        p { margin: 0 0 10px; }
        ul { margin: 0 0 10px; padding-left: 18px; }
        li { margin-bottom: 6px; }
        .footer { margin-top: 30px; font-size: 9pt; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 12px; }
    </style>
</head>
<body>
    <h1>{{ $document['title'] ?? '' }}</h1>
    @if(!empty($document['tagline']))
        <p class="meta">{{ $document['tagline'] }}</p>
    @endif
    <p class="meta">Last updated: {{ $document['last_updated'] ?? '' }}</p>

    @if(!empty($document['summary']))
        <div class="summary">
            <h2>Plain-English summary</h2>
            <ul>
                @foreach($document['summary'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach($document['sections'] ?? [] as $section)
        <section>
            <h2>{{ $section['title'] ?? '' }}</h2>
            @foreach($section['paragraphs'] ?? [] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            @if(!empty($section['bullets']))
                <ul>
                    @foreach($section['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach

    <p class="footer">{{ $platformName }} · {{ $document['title'] ?? '' }} · Generated {{ now()->timezone('Africa/Lagos')->format('j M Y') }}</p>
</body>
</html>
