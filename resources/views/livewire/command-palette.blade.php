<div>
    <div class="flex items-center gap-2 border-b border-gray-100 px-4">
        <x-lucide-search class="h-4 w-4 shrink-0 text-gray-400" />
        <input
            type="text"
            wire:model.live.debounce.150ms="query"
            @input="activeIndex = 0"
            placeholder="Search issues, projects…"
            autocomplete="off"
            class="w-full border-0 bg-transparent py-3.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0"
        >
        <kbd class="shrink-0 rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-xs font-medium text-gray-400">Esc</kbd>
    </div>

    <div class="max-h-96 overflow-y-auto p-2">
        @forelse($groups as $group => $items)
            <p class="px-2 pt-2 pb-1 text-xs font-medium tracking-wide text-gray-400 uppercase">{{ $group }}</p>
            @foreach($items as $item)
                <a
                    href="{{ $item['url'] }}"
                    data-command-item
                    x-bind:class="isActive($el) && 'bg-gray-100'"
                    @mouseenter="setActive($el)"
                    class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-gray-700"
                >
                    <x-dynamic-component :component="$item['icon']" class="h-4 w-4 shrink-0 text-gray-400" />
                    <span class="flex-1 truncate font-medium text-gray-900">{{ $item['label'] }}</span>
                    <span class="shrink-0 truncate text-xs text-gray-400">{{ $item['subtitle'] }}</span>
                </a>
            @endforeach
        @empty
            <p class="px-2.5 py-6 text-center text-sm text-gray-400">
                {{ $query !== '' ? 'No results found.' : 'Type to search issues and projects…' }}
            </p>
        @endforelse
    </div>
</div>
