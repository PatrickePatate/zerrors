<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ucfirst($frequency) }} digest for {{ $organizationName }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:16px; border:1px solid #e4e4e7; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 32px; border-bottom:1px solid #e4e4e7;">
                            <img src="{{ asset('images/zerrors-mail.png') }}" width="125" height="26" alt="Zerrors" style="display:block; border:0; height:26px; width:125px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 4px; font-size:18px; color:#0a0a0a;">{{ ucfirst($frequency) }} digest for {{ $organizationName }}</h1>
                            <p style="margin:0 0 24px; font-size:12px; color:#71717a;">{{ $periodStart->toFormattedDateString() }} to {{ $periodEnd->toFormattedDateString() }}</p>

                            @foreach($projects as $project)
                                <h2 style="margin:0 0 8px; font-size:15px; color:#0a0a0a;">{{ $project['name'] }}</h2>
                                <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#27272a;">
                                    <strong>{{ $project['new_issues'] }}</strong> new {{ \Illuminate\Support\Str::plural('issue', $project['new_issues']) }},
                                    <strong>{{ $project['regressions'] }}</strong> {{ \Illuminate\Support\Str::plural('regression', $project['regressions']) }},
                                    <strong>{{ number_format($project['events']) }}</strong> events
                                    (previous period: {{ number_format($project['previous_events']) }}).
                                </p>
                                @if(count($project['top_issues']) > 0)
                                    <ol style="margin:0 0 24px; padding-left:20px; font-size:14px; line-height:1.6; color:#27272a;">
                                        @foreach($project['top_issues'] as $issue)
                                            <li>
                                                @if($issue['url'])
                                                    <a href="{{ $issue['url'] }}" style="color:#0a0a0a; text-decoration:underline;">{{ $issue['title'] }}</a>
                                                @else
                                                    {{ $issue['title'] }}
                                                @endif
                                                ({{ number_format($issue['count']) }})
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            @endforeach

                            <p style="margin:8px 0 0; font-size:12px; color:#71717a;">You receive this digest because you opted in on {{ $organizationName }}.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
