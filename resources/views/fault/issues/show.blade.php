@extends('layouts.app')

@section('title', $issue->title.' · Zerrors')

@php
    $levelColors = ['error' => 'red', 'warning' => 'amber', 'fatal' => 'red', 'info' => 'blue'];
@endphp

@section('content')
    <a href="{{ route('organizations.projects.show', [$organization, $project]) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
        <x-lucide-arrow-left class="h-4 w-4" /> {{ $project->name }}
    </a>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <h1 class="text-lg font-semibold text-gray-900">{{ $issue->title }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $issue->culprit }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-badge :color="$levelColors[$issue->level] ?? 'gray'">{{ $issue->level }}</x-badge>
                    <span class="text-sm text-gray-400">
                        {{ $issue->times_seen }} events &middot; first seen {{ $issue->first_seen_at?->diffForHumans() }} &middot; last seen {{ $issue->last_seen_at?->diffForHumans() }}
                        @if($issue->first_seen_release) &middot; first seen in <code class="text-gray-500">{{ $issue->first_seen_release }}</code> @endif
                        @if($issue->regressed_at) &middot; <span class="text-amber-600">regressed {{ $issue->regressed_at->diffForHumans() }}</span> @endif
                    </span>
                </div>

                <div class="mt-4">
                    <livewire:issue-actions :organization="$organization" :project="$project" :issue="$issue" :event="$currentEvent" />
                </div>
            </x-card>

            @if($currentEvent)
                <x-card>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-medium text-gray-700">
                                Event {{ $eventNavigation['position'] }} of {{ $eventNavigation['total'] }}
                            </h2>
                            <p class="mt-0.5 text-xs text-gray-400">
                                {{ $currentEvent->occurred_at }} &middot; <code class="text-gray-500">{{ $currentEvent->event_id }}</code>
                                @if($currentEvent->environment) &middot; {{ $currentEvent->environment }} @endif
                                @if($currentEvent->release) &middot; {{ $currentEvent->release }} @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-1">
                            @php
                                $navLinks = [
                                    ['label' => 'Oldest', 'icon' => 'chevrons-left', 'target' => $eventNavigation['oldest']],
                                    ['label' => 'Older', 'icon' => 'chevron-left', 'target' => $eventNavigation['previous']],
                                    ['label' => 'Newer', 'icon' => 'chevron-right', 'target' => $eventNavigation['next']],
                                    ['label' => 'Newest', 'icon' => 'chevrons-right', 'target' => $eventNavigation['newest']],
                                ];
                            @endphp
                            @foreach($navLinks as $nav)
                                @php
                                    $isCurrent = $nav['target'] && $nav['target']->is($currentEvent);
                                    $disabled = ! $nav['target'] || $isCurrent;
                                @endphp
                                @if($disabled)
                                    <span title="{{ $nav['label'] }}" class="inline-flex items-center justify-center rounded-lg border border-gray-200 p-2 text-gray-300">
                                        <x-dynamic-component :component="'lucide-'.$nav['icon']" class="h-4 w-4" />
                                    </span>
                                @else
                                    <a href="{{ route('organizations.issues.events.show', [$organization, $project, $issue, $nav['target']->id]) }}"
                                       title="{{ $nav['label'] }}"
                                       class="inline-flex items-center justify-center rounded-lg border border-gray-300 p-2 text-gray-500 hover:bg-gray-50 hover:text-gray-900">
                                        <x-dynamic-component :component="'lucide-'.$nav['icon']" class="h-4 w-4" />
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </x-card>
            @endif

            @if($currentEvent && $currentEvent->exception)
                <x-card>
                    <h2 class="mb-3 text-sm font-medium text-gray-700">Stack trace</h2>
                    <x-stacktrace :exception="$currentEvent->exception" />
                </x-card>
            @elseif($currentEvent && $currentEvent->log_context)
                <x-card>
                    <h2 class="mb-3 text-sm font-medium text-gray-700">Log context</h2>
                    <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{{ json_encode($currentEvent->log_context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </x-card>
            @endif

            @if($currentEvent)
                <x-card>
                    <h2 class="mb-3 text-sm font-medium text-gray-700">User & context</h2>
                    @include('fault.issues.partials.context', ['event' => $currentEvent])
                </x-card>
            @endif

            <div>
                <h2 class="mb-3 text-sm font-medium text-gray-700">All events</h2>
                <x-table>
                    <x-table.head>
                        <x-table.column>Occurred</x-table.column>
                        <x-table.column>Message</x-table.column>
                        <x-table.column>Environment</x-table.column>
                        <x-table.column>Release</x-table.column>
                    </x-table.head>
                    <x-table.body>
                        @foreach($events as $event)
                            @php($isCurrent = $currentEvent && $event->is($currentEvent))
                            <x-table.row @class(['bg-indigo-50/60' => $isCurrent])>
                                <x-table.cell class="whitespace-nowrap">
                                    <a href="{{ route('organizations.issues.events.show', [$organization, $project, $issue, $event->id]) }}" class="block text-gray-700 hover:text-gray-900">
                                        @if($isCurrent)
                                            <span class="font-medium text-indigo-700">{{ $event->occurred_at }}</span>
                                        @else
                                            {{ $event->occurred_at }}
                                        @endif
                                    </a>
                                </x-table.cell>
                                <x-table.cell class="max-w-xs">
                                    <a href="{{ route('organizations.issues.events.show', [$organization, $project, $issue, $event->id]) }}" class="block truncate text-gray-700 hover:text-gray-900">
                                        {{ $event->message ?: '—' }}
                                    </a>
                                </x-table.cell>
                                <x-table.cell class="whitespace-nowrap text-gray-500">{{ $event->environment ?: '—' }}</x-table.cell>
                                <x-table.cell class="whitespace-nowrap text-gray-500">{{ $event->release ?: '—' }}</x-table.cell>
                            </x-table.row>
                        @endforeach
                    </x-table.body>
                </x-table>

                <div class="mt-4">{{ $events->links() }}</div>
            </div>
        </div>

        <div class="lg:sticky lg:top-6 lg:self-start">
            <livewire:issue-analysis :issue="$issue" />
        </div>
    </div>
@endsection
