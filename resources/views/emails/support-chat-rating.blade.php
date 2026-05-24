<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rate your support experience</title>
</head>
<body style="margin:0;padding:0;background:#f0fdfa;font-family:system-ui,-apple-system,Segoe UI,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdfa;padding:32px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:20px;border:1px solid #99f6e4;overflow:hidden;">
                <tr>
                    <td style="background:linear-gradient(135deg,#0f766e,#14b8a6);padding:28px 32px;">
                        <p style="margin:0;font-size:11px;font-weight:800;letter-spacing:0.2em;text-transform:uppercase;color:#ccfbf1;">HustleSafe Support</p>
                        <h1 style="margin:8px 0 0;font-size:22px;font-weight:900;color:#ffffff;">Your chat has ended</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">
                            Hi {{ $ticket->customer_full_name ?? $ticket->customer?->name ?? 'there' }},
                        </p>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">
                            Thanks for chatting with us about <strong>{{ $ticket->subject }}</strong>.
                            This session is now closed — you can open a new chat anytime if you need more help.
                        </p>
                        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#334155;">
                            We would love a quick review of your experience (about 1 minute). Pick a mood below or use the main button.
                        </p>
                        <table cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px;">
                            <tr>
                                @foreach (config('customer_support.closure_reactions', []) as $reaction)
                                <td align="center" style="padding:4px;">
                                    <a href="{{ $ratingUrl }}&reaction={{ $reaction['key'] }}"
                                       style="display:block;text-decoration:none;font-size:28px;line-height:1;"
                                       title="{{ $reaction['label'] }}">{{ $reaction['emoji'] }}</a>
                                </td>
                                @endforeach
                            </tr>
                        </table>
                        <table cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td align="center">
                                    <a href="{{ $ratingUrl }}"
                                       style="display:inline-block;padding:14px 28px;border-radius:12px;background:#0f766e;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none;">
                                        Leave feedback
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:24px 0 0;font-size:12px;color:#94a3b8;text-align:center;">
                            One review per session. This secure link expires in 14 days.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
