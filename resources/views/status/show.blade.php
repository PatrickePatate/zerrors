@extends('layouts.status')

@section('title', $status['title'])

@php
    $banner = match ($status['overall_status']) {
        'operational' => ['All systems operational', 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300'],
        'partial_outage' => ['Partial outage', 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300'],
        default => ['Major outage', 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300'],
    };
    $statusLabels = ['up' => 'Operational', 'down' => 'Down', 'unknown' => 'Unknown'];
    $statusColors = ['up' => 'text-green-600 dark:text-green-400', 'down' => 'text-red-600 dark:text-red-400', 'unknown' => 'text-gray-400'];
    $cellColor = function (?float $uptime): string {
        return match (true) {
            $uptime === null => 'bg-gray-200 dark:bg-gray-800',
            $uptime >= 99.9 => 'bg-green-500',
            $uptime >= 95 => 'bg-amber-400',
            default => 'bg-red-500',
        };
    };
@endphp

@section('content')
    <h1 class="text-2xl font-semibold">{{ $status['title'] }}</h1>
    @if($status['description'])
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $status['description'] }}</p>
    @endif

    <div class="mt-6 rounded-lg border px-4 py-3 text-sm font-medium {{ $banner[1] }}">{{ $banner[0] }}</div>

    @if(count($status['active_incidents']))
        <h2 class="mb-2 mt-8 text-sm font-medium text-gray-700 dark:text-gray-300">Active incidents</h2>
        <div class="space-y-3">
            @foreach($status['active_incidents'] as $incident)
                @include('status.incident', ['incident' => $incident])
            @endforeach
        </div>
    @endif

    <h2 class="mb-2 mt-8 text-sm font-medium text-gray-700 dark:text-gray-300">Services</h2>
    <div class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
        @forelse($status['monitors'] as $monitor)
            <div class="p-4">
                <div class="mb-2 flex items-center justify-between gap-3">
                    <span class="truncate font-medium">{{ $monitor['name'] }}</span>
                    <span class="shrink-0 text-sm font-medium {{ $statusColors[$monitor['status']] ?? 'text-gray-400' }}">{{ $statusLabels[$monitor['status']] ?? 'Unknown' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex h-8 min-w-0 flex-1 gap-px">
                        @foreach($monitor['days'] as $day)
                            <div class="h-full min-w-0 flex-1 rounded-[1px] {{ $cellColor($day['uptime']) }}"
                                 title="{{ $day['date'] }}: {{ $day['uptime'] === null ? 'no data' : $day['uptime'].'% uptime' }}"></div>
                        @endforeach
                    </div>
                    <span class="w-16 shrink-0 text-right text-sm text-gray-600 dark:text-gray-400">{{ $monitor['uptime'] === null ? '—' : $monitor['uptime'].'%' }}</span>
                </div>
                <div class="mt-1 flex justify-between text-xs text-gray-400">
                    <span>{{ \App\Support\Status\StatusPageData::DAYS }} days ago</span>
                    <span class="mr-[4.75rem]">Today</span>
                </div>
            </div>
        @empty
            <p class="p-4 text-sm text-gray-500">No services are listed yet.</p>
        @endforelse
    </div>

    <h2 class="mb-2 mt-8 text-sm font-medium text-gray-700 dark:text-gray-300">Past incidents (last {{ \App\Support\Status\StatusPageData::HISTORY_DAYS }} days)</h2>
    <div class="space-y-3">
        @forelse($status['past_incidents'] as $incident)
            @include('status.incident', ['incident' => $incident])
        @empty
            <p class="text-sm text-gray-500">No incidents reported.</p>
        @endforelse
    </div>
@endsection
