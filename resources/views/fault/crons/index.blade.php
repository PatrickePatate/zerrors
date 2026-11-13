@extends('layouts.app')

@section('title', 'Cron jobs · '.$project->name.' · Zerrors')

@section('content')
    @php($statusColors = ['ok' => 'green', 'error' => 'red', 'missed' => 'amber', 'timeout' => 'red', 'in_progress' => 'blue', 'unknown' => 'gray'])

    <a href="{{ route('organizations.projects.show', [$organization, $project]) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
        <x-lucide-arrow-left class="h-4 w-4" /> {{ $project->name }}
    </a>

    <h1 class="mb-4 text-2xl font-normal text-gray-900">Cron jobs</h1>

    @forelse($monitors as $monitor)
        <x-card class="mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-medium text-gray-900">{{ $monitor->name }}</h2>
                    <p class="text-xs text-gray-400"><code>{{ $monitor->slug }}</code> &middot; {{ $monitor->scheduleLabel() }} ({{ $monitor->timezone }})</p>
                </div>
                <x-badge :color="$statusColors[$monitor->status] ?? 'gray'">{{ ucfirst($monitor->status) }}</x-badge>
            </div>
            <p class="mt-2 text-sm text-gray-500">
                Last check-in: {{ $monitor->last_check_in_at?->diffForHumans() ?? 'never' }}
                &middot; Next expected: {{ $monitor->next_expected_at?->diffForHumans() ?? '—' }}
            </p>

            @if($monitor->checkIns->isNotEmpty())
                <ul class="mt-3 divide-y divide-gray-100 text-sm">
                    @foreach($monitor->checkIns as $checkIn)
                        <li class="flex items-center justify-between gap-3 py-1.5">
                            <span class="text-gray-500"><x-local-time :at="$checkIn->created_at" /></span>
                            <span class="flex items-center gap-3 text-gray-400">
                                @if($checkIn->duration !== null) {{ $checkIn->duration }}s @endif
                                @if($checkIn->environment) {{ $checkIn->environment }} @endif
                                <x-badge :color="$statusColors[$checkIn->status] ?? 'gray'">{{ str_replace('_', ' ', $checkIn->status) }}</x-badge>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @empty
        <x-card><p class="text-sm text-gray-400">No cron monitors yet. They appear when your SDK sends its first check-in.</p></x-card>
    @endforelse
@endsection
