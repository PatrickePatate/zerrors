@props(['issue'])

@php
    $event = $issue->latestEvent;
@endphp

@if($issue->isLog())
    <x-tooltip message="Log entry">
        <x-lucide-scroll-text class="h-3.5 w-3.5 text-gray-400" />
    </x-tooltip>
@endif

@if($event?->livewireComponent())
    <x-tooltip message="<b>Livewire error:</b> {{ $event->livewireComponent() }}">
        <x-icon-livewire class="h-3.5 w-3.5 text-indigo-500" />
    </x-tooltip>
@endif

@if($event?->isQueuedJob())
    <x-tooltip message="<b>Queue job:</b> {{ $event->queueJob() ?? 'unknown job class' }}">
        <x-lucide-list-todo class="h-3.5 w-3.5 text-indigo-500" />
    </x-tooltip>
@endif

@if($event?->isLikelyBot())
    <x-tooltip message="Likely triggered by a bot: {{ $event->browserLabel() }}">
        <x-lucide-bot class="h-3.5 w-3.5 text-amber-500" />
    </x-tooltip>
@endif
