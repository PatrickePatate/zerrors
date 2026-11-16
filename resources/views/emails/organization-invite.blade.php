<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>You've been invited to {{ $organizationName }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:16px; border:1px solid #e4e4e7; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 32px; border-bottom:1px solid #e4e4e7;">
                            <img src="{{ asset('images/zerrors-mail.png') }}" width="125" height="26" alt="Zerrors" style="display:block; border:0; height:26px; width:125px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 12px; font-size:18px; color:#0a0a0a;">You've been invited to {{ $organizationName }}</h1>
                            <p style="margin:0 0 20px; font-size:14px; line-height:1.6; color:#27272a;">
                                @if($inviterName)
                                    {{ $inviterName }} invited you
                                @else
                                    You've been invited
                                @endif
                                to join <strong>{{ $organizationName }}</strong> on Zerrors as <strong>{{ $role }}</strong>.
                            </p>
                            <a href="{{ $acceptUrl }}" style="display:inline-block; background:#0a0a0a; color:#ffffff; text-decoration:none; padding:10px 20px; border-radius:999px; font-size:14px; font-weight:600;">
                                Accept invite
                            </a>
                            @if($expiresAt)
                                <p style="margin:24px 0 0; font-size:12px; color:#71717a;">This invite expires {{ $expiresAt->diffForHumans() }}.</p>
                            @endif
                            <p style="margin:8px 0 0; font-size:12px; color:#71717a;">If the button doesn't work, copy this link: {{ $acceptUrl }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
