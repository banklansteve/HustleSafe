<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Welcome to HustleSafe</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Figtree,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(15,118,110,0.15);border:1px solid #e2e8f0;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0d9488 0%,#115e59 50%,#134e4a 100%);padding:28px 32px;text-align:center;">
                            <p style="margin:0 0 8px 0;font-size:11px;font-weight:800;letter-spacing:0.28em;text-transform:uppercase;color:rgba(255,255,255,0.85);">HustleSafe</p>
                            <h1 style="margin:0;font-size:22px;line-height:1.25;font-weight:800;color:#ffffff;">You made it in — nice one.</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            @php
                                $first = trim((string) ($user->first_name ?? ''));
                                $greet = $first !== '' ? $first : explode(' ', trim((string) $user->name))[0];
                            @endphp
                            <p style="margin:0 0 16px 0;font-size:17px;line-height:1.5;color:#0f172a;font-weight:700;">Hi {{ $greet }},</p>
                            <p style="margin:0 0 16px 0;font-size:16px;line-height:1.65;color:#334155;">
                                Welcome to the crew. HustleSafe is built for Nigerians who want <strong style="color:#0f766e;">real work, real escrow, and real payouts</strong> — without the “I never saw my money” drama.
                            </p>
                            <p style="margin:0 0 24px 0;font-size:16px;line-height:1.65;color:#334155;">
                                Before you explore quests and offers, we need one tiny thing: <strong style="color:#0f172a;">confirm this email is yours</strong>. Tap the button below — it keeps your account safe and unlocks the full dashboard.
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto 28px auto;">
                                <tr>
                                    <td style="border-radius:14px;background:linear-gradient(180deg,#14b8a6 0%,#0d9488 100%);box-shadow:0 10px 25px -8px rgba(13,148,136,0.55);">
                                        <a href="{{ $verificationUrl }}" target="_blank" rel="noopener" style="display:inline-block;padding:16px 36px;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:14px;">Verify my email &amp; let’s go</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px 0;font-size:13px;line-height:1.6;color:#64748b;">
                                Button acting shy? Paste this link into your browser:
                            </p>
                            <p style="margin:0 0 24px 0;font-size:12px;line-height:1.5;color:#0d9488;word-break:break-all;">
                                <a href="{{ $verificationUrl }}" style="color:#0d9488;">{{ $verificationUrl }}</a>
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
                                This link expires in {{ (int) config('auth.verification.expire', 60) }} minutes. If you didn’t create a HustleSafe account, you can ignore this — nothing will change.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f0fdfa;border-radius:14px;border:1px solid #ccfbf1;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <p style="margin:0 0 6px 0;font-size:12px;font-weight:800;letter-spacing:0.12em;text-transform:uppercase;color:#0f766e;">Quick tip</p>
                                        <p style="margin:0;font-size:14px;line-height:1.55;color:#134e4a;">
                                            Clients post <strong>Quests</strong>, freelancers send <strong>Offers</strong>, and escrow holds the money until you’re happy. Simple.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px 32px;text-align:center;border-top:1px solid #f1f5f9;">
                            <p style="margin:20px 0 4px 0;font-size:12px;color:#94a3b8;">© {{ date('Y') }} HustleSafe · Escrow-first marketplace</p>
                            <p style="margin:0;font-size:12px;color:#cbd5e1;">Built for Nigerian freelancers &amp; clients</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
