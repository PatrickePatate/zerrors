@props(['organization' => null])

{{--
    Global command palette (cmd+k), modelled on the Pines UI "Command" component:
    https://devdojo.com/pines/docs/command
    Alpine owns the open/close state and keyboard navigation (arrow keys move a
    roving `data-command-item` highlight, enter clicks it); the actual search
    results come from the wrapped Livewire component so they can hit the database.
--}}
<div
    x-data="commandPalette"
    @keydown.window="onGlobalKeydown($event)"
    @keydown.window.escape="open = false"
>
    <button
        type="button"
        @click="open = true"
        class="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-400 hover:border-gray-300 hover:text-gray-500"
    >
        <x-lucide-search class="h-4 w-4" />
        <span class="flex-1 text-left">Search…</span>
        <kbd class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-xs font-medium text-gray-400">⌘K</kbd>
    </button>

    <template x-teleport="body">
        <div x-show="open" style="display: none" class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[15vh]">
            <div
                x-show="open"
                x-transition:enter="ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-900/40"
            ></div>

            <div
                x-show="open"
                x-transition:enter="ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="open = false"
                @keydown.arrow-down.prevent="moveActive(1)"
                @keydown.arrow-up.prevent="moveActive(-1)"
                @keydown.enter.prevent="selectActive()"
                x-ref="panel"
                class="relative flex w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-xl"
            >
                <livewire:command-palette :organization="$organization" />
            </div>
        </div>
    </template>
</div>
