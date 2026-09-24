@php
    $isGuest = $isGuest ?? false;
    $levelColors = ['error' => 'red', 'warning' => 'amber', 'fatal' => 'red', 'info' => 'blue'];
@endphp

<x-card>
    <livewire:issue-status-badge :issue="$issue" />
    <h1 class="flex flex-wrap items-center gap-2 text-lg font-semibold text-gray-900">
        {{ $issue->title }}
    </h1>
    <p class="mt-1 text-sm text-gray-500">{{ $issue->culprit }}</p>

    @php
        $environment = $currentEvent?->environment;
    @endphp
    <div class="mt-3 mb-2 flex flex-wrap items-center gap-2">
        <x-badge :color="$levelColors[$issue->level] ?? 'gray'">{{ ucfirst($issue->level) }}</x-badge>
        <x-badge :color="$environment === 'production' ? 'amber' : 'gray'">{{$environment}}</x-badge>
        @if($livewireComponent = $currentEvent?->livewireComponent())
            <x-tooltip message="<b>Livewire error:</b> {{ $livewireComponent }}">
                <x-badge color="indigo">
                    <x-icon-livewire class="mr-1 -ms-0.5 inline h-3 w-3" />{{ class_basename($livewireComponent) }}
                </x-badge>
            </x-tooltip>
        @endif
        @if($currentEvent?->isQueuedJob())
            <x-tooltip message="<b>Queue job:</b> {{ $currentEvent->queueJob() ?? 'unknown job class' }}">
                <x-badge color="indigo">
                    <x-lucide-list-todo class="mr-1 -ms-0.5 inline h-3 w-3" />{{ $currentEvent->queueJob() ? class_basename($currentEvent->queueJob()) : 'Queue' }}
                </x-badge>
            </x-tooltip>
        @endif
        <div class="text-sm text-gray-400">
            <b>{{ $issue->times_seen }}</b> events
        </div>
    </div>
    <div class="text-sm text-gray-400">
        <x-lucide-eye class="w-4 h-4 inline"/> First seen {{ $issue->first_seen_at?->diffForHumans() }}, last seen {{ $issue->last_seen_at?->diffForHumans() }}.
    </div>
    @if($issue->first_seen_release || $issue->regressed_at)
        <div class="inline-flex gap-2 items-center text-sm text-gray-400">
            @if($issue->first_seen_release)
                <x-lucide-rocket class="w-4 h-4 inline"/> First seen in <code class="text-gray-500">{{ $issue->first_seen_release }}</code>
            @endif
            @if($issue->regressed_at) <span class="text-amber-600">Regressed {{ $issue->regressed_at->diffForHumans() }}</span> @endif
        </div>
    @endif

    @unless($isGuest)
        <div class="mt-4">
            <livewire:issue-actions :organization="$organization" :project="$project" :issue="$issue" :event="$currentEvent" :members="$members" />
        </div>
    @endunless
</x-card>

@if($currentEvent && ! $isGuest && $eventNavigation)
    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-medium text-gray-700">
                    Event {{ $eventNavigation['position'] }} of {{ $eventNavigation['total'] }}
                </h2>
                <p class="mt-0.5 text-xs text-gray-400">
                    <x-local-time :at="$currentEvent->occurred_at" /> &middot; <code class="text-gray-500">{{ $currentEvent->event_id }}</code>
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
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-medium text-gray-700">Stack trace</h2>
            <button type="button"
                    x-data="{ copied: false }"
                    @click="$clipboard(@js(\App\Support\Fault\StacktraceMarkdownFormatter::format($issue, $currentEvent))); copied = true; clearTimeout($el._copiedTimeout); $el._copiedTimeout = setTimeout(() => copied = false, 1500)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-600 transition hover:bg-gray-50 hover:text-gray-900">
                <x-lucide-copy-check x-cloak x-show="copied" class="h-3.5 w-3.5 text-emerald-600" />
                <x-lucide-copy x-show="!copied" class="h-3.5 w-3.5" />
                <span x-text="copied ? 'Copied!' : 'Copy as Markdown'"></span>
            </button>
        </div>
        <x-stacktrace :exception="$currentEvent->exception" />
    </x-card>
@elseif($currentEvent && $currentEvent->log_context)
    <x-card>
        <h2 class="mb-3 text-sm font-medium text-gray-700">Log context</h2>
        <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{{ json_encode($currentEvent->log_context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </x-card>
@endif

@if($isGuest)
    @include('fault.issues._ai-analysis-readonly')
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
                    @if($isGuest)
                        <x-table.cell class="whitespace-nowrap">
                            @if($isCurrent)
                                <span class="font-medium text-indigo-700"><x-local-time :at="$event->occurred_at" /></span>
                            @else
                                <x-local-time :at="$event->occurred_at" />
                            @endif
                        </x-table.cell>
                        <x-table.cell class="max-w-xs truncate">{{ $event->message ?: '—' }}</x-table.cell>
                    @else
                        <x-table.cell class="whitespace-nowrap">
                            <a href="{{ route('organizations.issues.events.show', [$organization, $project, $issue, $event->id]) }}" class="block text-gray-700 hover:text-gray-900">
                                @if($isCurrent)
                                    <span class="font-medium text-indigo-700"><x-local-time :at="$event->occurred_at" /></span>
                                @else
                                    <x-local-time :at="$event->occurred_at" />
                                @endif
                            </a>
                        </x-table.cell>
                        <x-table.cell class="max-w-xs">
                            <a href="{{ route('organizations.issues.events.show', [$organization, $project, $issue, $event->id]) }}" class="block truncate text-gray-700 hover:text-gray-900">
                                {{ $event->message ?: '—' }}
                            </a>
                        </x-table.cell>
                    @endif
                    <x-table.cell class="whitespace-nowrap text-gray-500">{{ $event->environment ?: '—' }}</x-table.cell>
                    <x-table.cell class="whitespace-nowrap text-gray-500">{{ $event->release ?: '—' }}</x-table.cell>
                </x-table.row>
            @endforeach
        </x-table.body>
    </x-table>

    <div class="mt-4">{{ $events->links() }}</div>
</div>
