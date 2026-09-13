@php
    $statusColors = ['unresolved' => 'red', 'resolved' => 'green', 'ignored' => 'gray'];
@endphp

<x-badge :color="$statusColors[$issue->status] ?? 'gray'" class="-ms-0.5 mb-1.5">{{ ucfirst($issue->status) }}</x-badge>
