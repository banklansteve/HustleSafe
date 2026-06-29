<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Dispute opened') }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:system-ui,-apple-system,Segoe UI,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:32px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:20px;border:1px solid #e2e8f0;overflow:hidden;">
                <tr>
                    <td style="background:linear-gradient(135deg,#0f766e,#0d9488);padding:28px 32px;">
                        <img src="{{ \App\Support\BrandedMail::logoUrl() }}" alt="{{ \App\Support\BrandedMail::brandName() }}" style="max-width:180px;height:auto;margin:0 auto 16px;display:block;">
                        <p style="margin:0 0 6px;font-size:11px;font-weight:800;letter-spacing:0.14em;text-transform:uppercase;color:#ccfbf1;">{{ __('Dispute notice') }}</p>
                        <h1 style="margin:0;font-size:22px;font-weight:900;line-height:1.3;color:#ffffff;">{{ __('A dispute was opened on your job') }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#334155;">
                            {{ __('Hello :name,', ['name' => $recipient->first_name ?: $recipient->name]) }}
                        </p>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.65;color:#334155;">
                            <strong>{{ $opener->name }}</strong> {{ __('(the :role) opened a formal dispute on', ['role' => $recipientRole === 'client' ? __('freelancer') : __('client')]) }}
                            <strong>“{{ $dispute->quest?->title }}”</strong>.
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;">
                            <tr>
                                <td style="padding:18px 20px;">
                                    <p style="margin:0 0 8px;font-size:11px;font-weight:800;letter-spacing:0.12em;text-transform:uppercase;color:#64748b;">{{ __('Case details') }}</p>
                                    <p style="margin:0 0 6px;font-size:14px;line-height:1.55;color:#0f172a;"><strong>{{ __('Reference') }}:</strong> {{ $dispute->displayReference() }}</p>
                                    <p style="margin:0 0 6px;font-size:14px;line-height:1.55;color:#0f172a;"><strong>{{ __('Reason') }}:</strong> {{ \App\Enums\QuestDisputeReason::tryFrom((string) $dispute->reason)?->label() ?? $dispute->reason }}</p>
                                    <p style="margin:0;font-size:14px;line-height:1.55;color:#0f172a;"><strong>{{ __('Contract value') }}:</strong> ₦{{ number_format(((int) $dispute->disputed_amount_minor) / 100) }}</p>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 10px;font-size:13px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#0f766e;">{{ __('What you need to do now') }}</p>
                        <ol style="margin:0 0 24px;padding-left:20px;font-size:14px;line-height:1.7;color:#334155;">
                            <li style="margin-bottom:8px;">{{ __('Open the dispute file and read the full description and evidence.') }}</li>
                            <li style="margin-bottom:8px;">{{ __('Respond within :hours hours with your version of events and any supporting files.', ['hours' => $responseHours]) }}</li>
                            <li style="margin-bottom:8px;">{{ __('You may propose a settlement split or agree to resolve mutually if aligned with the other party.') }}</li>
                            <li>{{ __('If timers expire without a response, the case escalates to HustleSafe staff review.') }}</li>
                        </ol>

                        <table cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ route('disputes.show', $dispute, true) }}" style="display:inline-block;padding:14px 26px;border-radius:9999px;background:#0f766e;color:#ffffff;text-decoration:none;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:0.04em;">{{ __('View dispute & respond') }}</a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 10px;font-size:13px;line-height:1.6;color:#64748b;">
                            {{ __('HustleSafe does not charge parties a dispute resolution fee. Accounts involved in more than three disputes may be flagged for a trust review.') }}
                        </p>
                        <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
                            {{ __('Both parties see the same dispute thread and audit trail. Decisions are based on dated evidence, not private side channels.') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
