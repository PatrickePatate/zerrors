@php
    $statusColors = ['unresolved' => 'red', 'resolved' => 'green', 'ignored' => 'gray'];
@endphp

<x-badge :color="$statusColors[$issue->status] ?? 'gray'" class="-ms-0.5 mb-1.5">{{ ucfirst($issue->status) }}</x-badge>
@if($issue->status === 'ignored' && ($issue->snoozed_until || $issue->snooze_until_count))
    <span class="ms-1 text-xs text-gray-400">
        @if($issue->snoozed_until) until {{ $issue->snoozed_until->diffForHumans() }} @endif
        @if($issue->snooze_until_count) until {{ number_format($issue->snooze_until_count) }} events @endif
    </span>
@elseif($issue->status === 'resolved' && ($issue->resolved_in_release || $issue->resolved_in_next_release))
    <span class="ms-1 text-xs text-gray-400">in {{ $issue->resolved_in_release ?? 'next release' }}</span>
@endif
