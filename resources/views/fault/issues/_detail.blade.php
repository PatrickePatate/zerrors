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

@php
    $eventFilters = $eventFilters ?? [];
    $eventFilterOptions = $eventFilterOptions ?? collect();
    $eventColumns = [
        'message' => 'Message', 'environment' => 'Environment', 'release' => 'Release',
        'client_ip' => 'Client IP', 'browser' => 'Browser', 'os' => 'OS', 'server_name' => 'Server', 'level' => 'Level',
    ];
    $defaultEventColumns = ['message', 'environment', 'release', 'client_ip', 'browser'];
    $selectClasses = 'h-9 rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900/10';
@endphp

<div x-data="{
        columns: @js($eventColumns),
        visible: @js($defaultEventColumns),
        storageKey: 'zerrors.issue-event-columns',
        open: false,
        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(this.storageKey));
                if (Array.isArray(saved)) this.visible = saved.filter(key => key in this.columns);
            } catch (e) {}
        },
        toggle(key) {
            this.visible = this.visible.includes(key) ? this.visible.filter(k => k !== key) : [...this.visible, key];
            try { localStorage.setItem(this.storageKey, JSON.stringify(this.visible)); } catch (e) {}
        },
    }">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-medium text-gray-700">All events</h2>

        <div class="relative">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                <x-lucide-columns-3 class="h-3.5 w-3.5" /> Columns
            </button>
            <div x-cloak x-show="open" @click.away="open = false" @keydown.escape.window="open = false"
                 class="absolute right-0 z-20 mt-1 w-44 space-y-1 rounded-md bg-white p-2 shadow-lg ring-1 ring-black/5">
                <template x-for="(label, key) in columns" :key="key">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/10"
                               :checked="visible.includes(key)" @change="toggle(key)">
                        <span x-text="label"></span>
                    </label>
                </template>
            </div>
        </div>
    </div>

    @unless($isGuest)
        <form method="GET" action="{{ url()->current() }}" class="mb-3 flex flex-wrap items-center gap-2">
            <input type="text" name="ip" value="{{ $eventFilters['ip'] ?? '' }}" placeholder="Client IP"
                   class="h-9 w-36 rounded-md border border-gray-300 px-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
            @foreach(['browser' => 'Browser', 'os' => 'OS', 'environment' => 'Environment', 'release' => 'Release'] as $filter => $filterLabel)
                <select name="{{ $filter }}" class="{{ $selectClasses }}">
                    <option value="">{{ $filterLabel }}: any</option>
                    @foreach($eventFilterOptions[$filter] ?? [] as $option)
                        <option value="{{ $option }}" @selected(($eventFilters[$filter] ?? null) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @endforeach
            <x-button type="submit">Filter</x-button>
            @if($eventFilters)
                <a href="{{ url()->current() }}" class="text-sm text-gray-500 hover:text-gray-900">Clear</a>
            @endif
        </form>
    @endunless

    <x-table>
        <x-table.head>
            <x-table.column>Occurred</x-table.column>
            @foreach($eventColumns as $key => $label)
                <x-table.column x-show="visible.includes('{{ $key }}')">{{ $label }}</x-table.column>
            @endforeach
        </x-table.head>
        <x-table.body>
            @forelse($events as $event)
                @php($isCurrent = $currentEvent && $event->is($currentEvent))
                @php($eventUrl = $isGuest ? null : route('organizations.issues.events.show', [$organization, $project, $issue, $event->id]))
                <x-table.row @class(['bg-indigo-50/60' => $isCurrent])>
                    <x-table.cell class="whitespace-nowrap">
                        @if($eventUrl) <a href="{{ $eventUrl }}" class="block text-gray-700 hover:text-gray-900"> @endif
                        <span @class(['font-medium text-indigo-700' => $isCurrent])><x-local-time :at="$event->occurred_at" /></span>
                        @if($eventUrl) </a> @endif
                    </x-table.cell>
                    <x-table.cell class="max-w-xs" x-show="visible.includes('message')">
                        @if($eventUrl)
                            <a href="{{ $eventUrl }}" class="block truncate text-gray-700 hover:text-gray-900">{{ $event->message ?: '—' }}</a>
                        @else
                            <span class="block truncate">{{ $event->message ?: '—' }}</span>
                        @endif
                    </x-table.cell>
                    @foreach(['environment', 'release', 'client_ip', 'browser', 'os', 'server_name', 'level'] as $key)
                        <x-table.cell class="whitespace-nowrap text-gray-500" x-show="visible.includes('{{ $key }}')">{{ $event->{$key} ?: '—' }}</x-table.cell>
                    @endforeach
                </x-table.row>
            @empty
                <x-table.row><x-table.cell colspan="9" class="text-center text-gray-400">No events match these filters.</x-table.cell></x-table.row>
            @endforelse
        </x-table.body>
    </x-table>

    <div class="mt-4">{{ $events->links() }}</div>
</div>
