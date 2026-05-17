<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Operations console access') }}</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #0f172a; background: #f8fafc; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 28px 24px; border: 1px solid #e2e8f0;">
        <h1 style="font-size: 20px; margin: 0 0 12px;">{{ __('Operations console access') }}</h1>
        <p style="margin: 0 0 12px;">{{ __('Hello :name,', ['name' => $user->first_name ?: $user->name]) }}</p>
        <p style="margin: 0 0 16px;">{{ __('You have been invited to join the HustleSafe operations team. Use the button below to choose your password, then sign in from the usual login page anytime.') }}</p>
        <p style="margin: 0 0 20px;">
            <a href="{{ $setupUrl }}" style="display: inline-block; background: #0d9488; color: #ffffff; text-decoration: none; font-weight: 700; padding: 12px 20px; border-radius: 12px;">{{ __('Set my password') }}</a>
        </p>
        <p style="margin: 0; font-size: 13px; color: #64748b;">{{ __('This link expires in seven days. If it stops working, use “Forgot password” on the login screen with this email address: :email.', ['email' => $user->email]) }}</p>
        <p style="margin: 20px 0 0; font-size: 13px; color: #64748b;">— {{ config('app.name') }}</p>
    </div>
</body>
</html>
