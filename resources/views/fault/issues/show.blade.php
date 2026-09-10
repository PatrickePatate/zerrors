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
                    <livewire:issue-actions :organization="$organization" :project="$project" :issue="$issue" />
                </div>
            </x-card>

            @php($latestEvent = $events->first())
            @if($latestEvent && $latestEvent->exception)
                <x-card>
                    <h2 class="mb-3 text-sm font-medium text-gray-700">Stack trace <span class="font-normal text-gray-400">(latest event)</span></h2>
                    <x-stacktrace :exception="$latestEvent->exception" />
                </x-card>
            @endif

            @if($latestEvent)
                <x-card>
                    <h2 class="mb-3 text-sm font-medium text-gray-700">User & context <span class="font-normal text-gray-400">(latest event)</span></h2>
                    @include('fault.issues.partials.context', ['event' => $latestEvent])
                </x-card>
            @endif

            <div>
                <h2 class="mb-3 text-sm font-medium text-gray-700">Recent events</h2>
                <div class="space-y-3">
                    @foreach($events as $event)
                        <x-card>
                            <p class="text-xs text-gray-400">
                                {{ $event->occurred_at }} &middot; <code class="text-gray-500">{{ $event->event_id }}</code>
                                @if($event->environment) &middot; {{ $event->environment }} @endif
                                @if($event->release) &middot; {{ $event->release }} @endif
                            </p>
                            <p class="mt-1 text-sm text-gray-800">{{ $event->message }}</p>
                            @if($event->tags)
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($event->tags as $key => $value)
                                        <x-badge color="blue">{{ $key }}: {{ $value }}</x-badge>
                                    @endforeach
                                </div>
                            @endif
                        </x-card>
                    @endforeach
                </div>

                <div class="mt-4">{{ $events->links() }}</div>
            </div>
        </div>

        <div class="lg:sticky lg:top-6 lg:self-start">
            <livewire:issue-analysis :issue="$issue" />
        </div>
    </div>
@endsection
