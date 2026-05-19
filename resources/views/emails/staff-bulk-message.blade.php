<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $messageRequest->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Figtree,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;overflow:hidden;box-shadow:0 24px 60px rgba(15,23,42,0.10);">
                    <tr>
                        <td style="padding:30px 34px;background:linear-gradient(135deg,#0d9488,#115e59);">
                            <p style="margin:0 0 8px;font-size:11px;font-weight:900;letter-spacing:.28em;text-transform:uppercase;color:#ccfbf1;">HustleSafe</p>
                            <h1 style="margin:0;font-size:24px;line-height:1.25;color:white;font-weight:900;">{{ $messageRequest->subject }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 34px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#334155;">Hi {{ $recipient->name ?? 'there' }},</p>
                            <div style="font-size:16px;line-height:1.75;color:#334155;white-space:pre-line;">{{ $messageRequest->body }}</div>
                            <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                                You are receiving this because you use HustleSafe. This message was reviewed and authorised by the HustleSafe admin team.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 34px;text-align:center;border-top:1px solid #f1f5f9;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;">© {{ date('Y') }} HustleSafe · Escrow-first marketplace</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
