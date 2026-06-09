<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quest Patrol Digest</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111; line-height: 1.5;">
    <h1 style="font-size: 20px;">Quest &amp; proposal patrol digest</h1>
    <p>Hi {{ $admin->name }},</p>
    <p>Summary for <strong>{{ $digest['date'] }}</strong>.</p>

    <ul>
        <li><strong>{{ $digest['new_flags_count'] }}</strong> new anomaly flag(s) in the last 24 hours</li>
        <li><strong>{{ $digest['summary']['open_high_severity'] ?? 0 }}</strong> open high-severity cases</li>
        <li>False positive rate (7d): <strong>{{ $digest['summary']['false_positive_rate_percent'] ?? 0 }}%</strong></li>
    </ul>

    @if(! empty($digest['high_risk']))
        <h2 style="font-size: 16px;">Top risks requiring attention</h2>
        <ul>
            @foreach($digest['high_risk'] as $item)
                <li>
                    {{ $item['label'] }} — {{ $item['subject_type'] }} #{{ $item['subject_id'] }}
                    ({{ $item['severity'] }}) · {{ $item['detected_at'] }}
                </li>
            @endforeach
        </ul>
    @endif

    <p>
        <a href="{{ $digest['moderation_url'] }}">Open moderation centre</a>
    </p>

    <p style="color: #666; font-size: 12px;">HustleSafe internal patrol notification</p>
</body>
</html>
