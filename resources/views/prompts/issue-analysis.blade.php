You will be given a raw exception stacktrace and some context captured alongside it. Your job is to:
1. Identify the most likely root cause (one or two sentences, no hedging unless genuinely ambiguous — if ambiguous, name the top 2 candidates).
2. Point to the exact file/line/function where the fault likely originates.
3. Give a brief, concrete fix — a code change, a config check, or a next diagnostic step if you can't be certain from the trace alone.
Keep the whole response under 150 words. No preamble, no restating the stacktrace back to me.
Exception type: {{ $issue->type }}
Message: {{ $issue->title }}
Culprit: {{ $issue->culprit ?: 'unknown' }}
Level: {{ $issue->level }}
Times seen: {{ $issue->times_seen }}
@if ($event?->environment)
Environment: {{ $event->environment }}
@endif
@if ($event?->release)
Release: {{ $event->release }}
@endif
@if ($event?->tags)
Tags: {{ collect($event->tags)->map(fn ($v, $k) => "{$k}={$v}")->implode(', ') }}
@endif
@if ($exception)

Stack trace (top of stack first):
{!! $frames !!}
@endif
