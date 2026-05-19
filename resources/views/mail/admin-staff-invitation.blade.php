<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Admin access') }}</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #0f172a; background: #f8fafc; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 30px 26px; border: 1px solid #e2e8f0;">
        <p style="margin: 0 0 8px; color: #0284c7; font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;">{{ config('app.name') }} Admin</p>
        <h1 style="font-size: 22px; margin: 0 0 14px;">{{ __('You have been added as an admin') }}</h1>
        <p style="margin: 0 0 12px;">{{ __('Hello :name,', ['name' => $user->first_name ?: $user->name]) }}</p>
        <p style="margin: 0 0 16px;">
            {{ __(':admin created an admin account for you on :app. To keep the account secure, choose your own password before accessing the admin dashboard.', ['admin' => $invitedBy->name, 'app' => config('app.name')]) }}
        </p>
        <p style="margin: 0 0 22px;">
            <a href="{{ $setupUrl }}" style="display: inline-block; background: #0284c7; color: #ffffff; text-decoration: none; font-weight: 800; padding: 13px 22px; border-radius: 14px;">{{ __('Set my admin password') }}</a>
        </p>
        <p style="margin: 0 0 10px; font-size: 13px; color: #64748b;">
            {{ __('This secure link expires in seven days. After setting your password, you will be signed in and taken to the admin dashboard.') }}
        </p>
        <p style="margin: 0; font-size: 13px; color: #64748b;">
            {{ __('If you were not expecting admin access, ignore this email and contact the HustleSafe team.') }}
        </p>
        <p style="margin: 22px 0 0; font-size: 13px; color: #64748b;">— {{ config('app.name') }}</p>
    </div>
</body>
</html>
