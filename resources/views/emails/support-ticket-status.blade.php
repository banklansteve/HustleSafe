<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HustleSafe Support Ticket</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Figtree,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;overflow:hidden;box-shadow:0 24px 60px rgba(15,23,42,0.10);">
                    <tr>
                        <td style="padding:30px 34px;background:linear-gradient(135deg,#0f766e,#0f172a);">
                            <p style="margin:0 0 8px;font-size:11px;font-weight:900;letter-spacing:.28em;text-transform:uppercase;color:#ccfbf1;">HustleSafe</p>
                            <h1 style="margin:0;font-size:24px;line-height:1.25;color:white;font-weight:900;">
                                {{ $event === 'closed' ? 'Your support ticket is closed' : 'Your support ticket is open' }}
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 34px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#334155;">
                                Hi {{ $ticket->customer?->name ?? 'there' }},
                            </p>
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.7;color:#334155;">
                                {{ $event === 'closed'
                                    ? 'We have closed your support ticket after completing the review. The summary is below for your records.'
                                    : 'We have opened a support ticket for your request. Our operations team will keep the details organised here until it is resolved.' }}
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0 0 6px;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#0f766e;">Ticket #{{ $ticket->id }}</p>
                                        <h2 style="margin:0 0 12px;font-size:19px;line-height:1.35;color:#0f172a;">{{ $ticket->subject }}</h2>
                                        <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#475569;"><strong>Status:</strong> {{ str_replace('_', ' ', ucfirst($ticket->status)) }}</p>
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#475569;">{{ $event === 'closed' ? ($ticket->resolution_summary ?: 'Resolved by the HustleSafe support team.') : ($ticket->description ?: 'The operations team will review and follow up.') }}</p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:22px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                                Please keep this email for reference. If you still need help, reply through your HustleSafe conversation or contact support.
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
