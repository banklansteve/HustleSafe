<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Operations console access') }}</title>
</head>
<body style="margin:0;padding:0;background:#f0fdfa;font-family:system-ui,-apple-system,Segoe UI,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdfa;padding:32px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;border:1px solid #99f6e4;overflow:hidden;">
                <tr>
                    <td style="background:linear-gradient(135deg,#0f766e,#14b8a6);padding:28px 32px;text-align:center;">
                        <img src="{{ \App\Support\BrandedMail::logoUrl() }}" alt="{{ \App\Support\BrandedMail::brandName() }}" style="max-width:200px;height:auto;margin:0 auto 12px;display:block;">
                        <h1 style="margin:0;font-size:22px;font-weight:900;color:#ffffff;">{{ __('Operations console access') }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#334155;">{{ __('Hello :name,', ['name' => $user->first_name ?: $user->name]) }}</p>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">{{ __('You have been invited to join the HustleSafe operations team. Use the button below to choose your password, then sign in from the usual login page anytime.') }}</p>
                        <table cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ $setupUrl }}" style="display:inline-block;padding:14px 24px;border-radius:9999px;background:#0f766e;color:#ffffff;text-decoration:none;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:0.04em;">{{ __('Set my password') }}</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">{{ __('This link expires in seven days. If it stops working, use “Forgot password” on the login screen with this email address: :email.', ['email' => $user->email]) }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
