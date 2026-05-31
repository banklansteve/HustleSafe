<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HustleSafe Support</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Figtree,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:24px;overflow:hidden;box-shadow:0 24px 60px rgba(15,23,42,0.10);">
                    <tr>
                        <td style="padding:30px 34px;background:linear-gradient(135deg,#0f766e,#14b8a6);text-align:center;">
                            <img src="{{ \App\Support\BrandedMail::logoUrl() }}" alt="{{ \App\Support\BrandedMail::brandName() }}" style="max-width:200px;height:auto;margin:0 auto 12px;display:block;">
                            <h1 style="margin:0;font-size:24px;line-height:1.25;color:white;font-weight:900;">
                                @if(in_array($event, ['closed', 'resolved'], true))
                                    Your support request is resolved
                                @elseif($event === 'update')
                                    Update on your support request
                                @else
                                    We received your support request
                                @endif
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 34px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#334155;">
                                Hi {{ $ticket->customer?->name ?? 'there' }},
                            </p>
                            <p style="margin:0 0 20px;font-size:16px;line-height:1.7;color:#334155;">
                                @if(in_array($event, ['closed', 'resolved'], true))
                                    Your support request has been marked resolved. Our team has reviewed the issue and documented the outcome below.
                                @elseif($event === 'update')
                                    Our support team posted an update regarding your request. You can review the latest information below.
                                @else
                                    Thank you for contacting HustleSafe support. We have logged your request and our operations team will work on it within <strong>10 working days</strong>.
                                @endif
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0 0 6px;font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#0f766e;">Reference {{ $ticket->ticket_reference ?: ('#'.$ticket->id) }}</p>
                                        <h2 style="margin:0 0 12px;font-size:19px;line-height:1.35;color:#0f172a;">{{ $ticket->subject }}</h2>
                                        <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#475569;"><strong>Category:</strong> {{ $extra['issue_group_label'] ?? ucfirst(str_replace('_', ' ', (string) $ticket->issue_group)) }}</p>
                                        @if($ticket->expected_resolution_at && !in_array($event, ['closed', 'resolved'], true))
                                            <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#475569;"><strong>Expected response window:</strong> by {{ $ticket->expected_resolution_at->format('d M Y') }}</p>
                                        @endif
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#475569;">
                                            @if(in_array($event, ['closed', 'resolved'], true))
                                                {{ $extra['resolution_summary'] ?? $ticket->resolution_summary ?? 'Your request has been handled by the HustleSafe support team.' }}
                                            @elseif($event === 'update')
                                                {{ $extra['comment'] ?? 'Please sign in to HustleSafe for full details.' }}
                                            @else
                                                {{ \Illuminate\Support\Str::limit(strip_tags((string) $ticket->description), 280) }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:22px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
                                Please keep this reference handy if you need to follow up. For your security, this email does not include internal support notes.
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
