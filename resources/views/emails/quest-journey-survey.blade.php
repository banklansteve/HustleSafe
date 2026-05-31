<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#f0fdfa;font-family:system-ui,-apple-system,Segoe UI,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdfa;padding:32px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;border:1px solid #99f6e4;overflow:hidden;">
                <tr>
                    <td style="background:linear-gradient(135deg,#0f766e,#14b8a6);padding:28px 32px;text-align:center;">
                        <img src="{{ \App\Support\BrandedMail::logoUrl() }}" alt="{{ \App\Support\BrandedMail::brandName() }}" style="max-width:200px;height:auto;margin:0 auto 12px;display:block;">
                        <h1 style="margin:0;font-size:22px;font-weight:900;color:#ffffff;">{{ $headline }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">
                            Hi {{ $survey->user?->first_name ?? $survey->user?->name ?? 'there' }},
                        </p>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">
                            {{ $opener }}
                        </p>
                        <p style="margin:0 0 8px;font-size:13px;font-weight:800;color:#0f766e;text-transform:uppercase;letter-spacing:0.08em;">
                            Quick question — tap one answer
                        </p>
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.5;font-weight:700;color:#0f172a;">
                            {{ $firstQuestionLabel }}
                        </p>
                        <table cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px;">
                            @foreach ($embeddedOptions as $option)
                            <tr>
                                <td style="padding:6px 0;">
                                    <a href="{{ $option['url'] }}"
                                       style="display:block;padding:14px 18px;border-radius:12px;background:#f0fdfa;border:1px solid #99f6e4;color:#0f766e;font-size:14px;font-weight:800;text-decoration:none;text-align:center;">
                                        {{ $option['label'] }}
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:#64748b;">
                            Your first tap saves that answer and opens a short anonymous survey for the rest — no login required.
                        </p>
                        <p style="margin:0;font-size:12px;color:#94a3b8;text-align:center;">
                            Responses are anonymous in our survey.
                            @if ($isReminder && $reminderKey === 'before_expiry')
                                This secure link closes in about {{ config('quest_journey_survey.reminders.before_expiry_hours', 6) }} hours.
                            @else
                                This secure link expires in {{ config('quest_journey_survey.link_ttl_days', 7) }} days.
                            @endif
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
