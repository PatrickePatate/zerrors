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
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; border:1px solid #e4e4e7; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 32px; border-bottom:1px solid #f0f0f1;">
                            <span style="font-size:16px; font-weight:700; color:#111827;">Zer<span style="color:#4f46e5;">rors</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 4px; font-size:18px; color:#111827;">{{ ucfirst($frequency) }} digest for {{ $organizationName }}</h1>
                            <p style="margin:0 0 24px; font-size:12px; color:#a1a1aa;">{{ $periodStart->toFormattedDateString() }} to {{ $periodEnd->toFormattedDateString() }}</p>

                            @foreach($projects as $project)
                                <h2 style="margin:0 0 8px; font-size:15px; color:#111827;">{{ $project['name'] }}</h2>
                                <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#52525b;">
                                    <strong>{{ $project['new_issues'] }}</strong> new {{ \Illuminate\Support\Str::plural('issue', $project['new_issues']) }},
                                    <strong>{{ $project['regressions'] }}</strong> {{ \Illuminate\Support\Str::plural('regression', $project['regressions']) }},
                                    <strong>{{ number_format($project['events']) }}</strong> events
                                    (previous period: {{ number_format($project['previous_events']) }}).
                                </p>
                                @if(count($project['top_issues']) > 0)
                                    <ol style="margin:0 0 24px; padding-left:20px; font-size:14px; line-height:1.6; color:#52525b;">
                                        @foreach($project['top_issues'] as $issue)
                                            <li>
                                                @if($issue['url'])
                                                    <a href="{{ $issue['url'] }}" style="color:#4f46e5; text-decoration:none;">{{ $issue['title'] }}</a>
                                                @else
                                                    {{ $issue['title'] }}
                                                @endif
                                                ({{ number_format($issue['count']) }})
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            @endforeach

                            <p style="margin:8px 0 0; font-size:12px; color:#a1a1aa;">You receive this digest because you opted in on {{ $organizationName }}.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
