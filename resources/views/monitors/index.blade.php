@extends('layouts.app')

@section('title', 'Monitoring · '.$organization->name.' · Zerrors')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">
            Monitoring
            @if($projectFilter)
                <span class="text-sm font-normal text-gray-400">&middot; filtered by project: {{ $projectFilter }}</span>
            @endif
        </h1>

        @if(in_array($organization->roleFor(auth()->user()), ['owner', 'admin']))
            <x-button :href="route('organizations.monitors.create', $organization)" tag="a">
                <x-lucide-plus class="h-4 w-4" />
                New monitor
            </x-button>
        @endif
    </div>

    <x-table>
        <x-table.head>
            <x-table.column>Status</x-table.column>
            <x-table.column>Name</x-table.column>
            <x-table.column>Type</x-table.column>
            <x-table.column>Project</x-table.column>
            <x-table.column>Last checked</x-table.column>
            <x-table.column></x-table.column>
        </x-table.head>
        <x-table.body>
            @php($canManage = in_array($organization->roleFor(auth()->user()), ['owner', 'admin'], true))
            @forelse($monitors as $monitor)
                {{--
                    Same pattern as the projects list (resources/views/fault/projects/index.blade.php):
                    the menu opens either from the trigger button or a right-click anywhere on the row,
                    so the Alpine scope lives on <x-table.row> itself rather than a nested <x-dropdown> —
                    a wrapping div around the whole row would break table layout. The panel is teleported
                    to <body> and positioned with fixed coordinates so it isn't clipped by the table's
                    overflow wrapper.
                --}}
                {{--
                    canManage travels in via a bound `:data-can-manage` attribute rather than
                    interpolating PHP inside the x-data string itself — Blade's component-tag
                    attribute compiler doesn't expand @directives (e.g. @js(...)) embedded in a
                    plain attribute value on a custom <x-...> tag, it leaves them as literal text.
                --}}
                <x-table.row
                    :data-can-manage="$canManage ? '1' : '0'"
                    x-data="{
                        menuOpen: false,
                        menuStyle: '',
                        openMenuAt(x, y) {
                            if (this.$root.dataset.canManage !== '1') return;
                            this.menuStyle = `top:${y}px; left:${x}px;`;
                            this.menuOpen = true;
                        },
                        openMenuBelow(el) {
                            if (this.$root.dataset.canManage !== '1') return;
                            const rect = el.getBoundingClientRect();
                            this.menuStyle = `top:${rect.bottom}px; left:${rect.right}px; transform: translateX(-100%);`;
                            this.menuOpen = true;
                        },
                    }"
                    @contextmenu.prevent="openMenuAt($event.clientX, $event.clientY)"
                    @keydown.escape.window="menuOpen = false"
                    @scroll.window.capture="menuOpen = false"
                    @resize.window="menuOpen = false"
                >
                    <x-table.cell>
                        <x-badge :color="$monitor->current_status->color()">{{ $monitor->current_status->label() }}</x-badge>
                    </x-table.cell>
                    <x-table.cell>
                        <a href="{{ route('organizations.monitors.show', [$organization, $monitor]) }}" class="font-semibold text-gray-900 hover:underline">{{ $monitor->name }}</a>
                        <div class="text-xs text-gray-400">{{ $monitor->url }}</div>
                    </x-table.cell>
                    <x-table.cell>
                        <div class="flex items-center gap-1.5 text-gray-600">{{ $monitor->type->icon('4') }} {{ $monitor->type->label() }}</div>
                    </x-table.cell>
                    <x-table.cell class="text-gray-600">{{ $monitor->project?->name ?? '—' }}</x-table.cell>
                    <x-table.cell class="text-gray-500">{{ $monitor->last_checked_at?->diffForHumans() ?? 'Never' }}</x-table.cell>
                    <x-table.cell class="text-right">
                        @if($canManage)
                            <button type="button" @click="openMenuBelow($event.currentTarget)" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                <x-lucide-ellipsis-vertical class="h-4 w-4" />
                            </button>

                            <template x-teleport="body">
                                <div
                                    x-show="menuOpen"
                                    x-on:click.away="menuOpen = false"
                                    x-transition:enter="ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-2"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="ease-in duration-100"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    x-cloak
                                    :style="menuStyle"
                                    class="fixed z-50 w-max min-w-max rounded-md border border-neutral-200/70 bg-white p-1 shadow-md text-neutral-700"
                                >
                                    <x-dropdown-link :href="route('organizations.monitors.show', [$organization, $monitor])" @click="menuOpen = false">
                                        <x-lucide-eye class="h-4 w-4" /> View
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('organizations.monitors.edit', [$organization, $monitor])" @click="menuOpen = false">
                                        <x-lucide-settings class="h-4 w-4" /> Edit
                                    </x-dropdown-link>

                                    <x-modal max-width="sm" :open-on-error="$errors->has('confirm_name') && old('monitor_id') == $monitor->id">
                                        <x-slot:trigger>
                                            <x-dropdown-link tag="button" type="button" @click="menuOpen = false" class="text-red-600 hover:bg-red-50">
                                                <x-lucide-trash-2 class="h-4 w-4" /> Delete monitor
                                            </x-dropdown-link>
                                        </x-slot:trigger>

                                        <h2 class="mb-1 flex items-center gap-1.5 text-sm font-medium text-red-700">
                                            <x-lucide-triangle-alert class="h-4 w-4" /> Delete monitor
                                        </h2>
                                        <p class="mb-3 text-sm text-gray-500">
                                            Deletes <strong>{{ $monitor->name }}</strong> and all of its check history. This cannot be undone.
                                        </p>
                                        <form method="POST" action="{{ route('organizations.monitors.destroy', [$organization, $monitor]) }}" x-data="{ confirmation: '' }" class="space-y-3">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="monitor_id" value="{{ $monitor->id }}">
                                            <div>
                                                <label for="confirm_name_{{ $monitor->id }}" class="mb-1 block text-sm font-medium text-gray-700">
                                                    Type <span class="font-semibold">Bye bye {{ $monitor->name }}</span> to confirm
                                                </label>
                                                <input type="text" id="confirm_name_{{ $monitor->id }}" name="confirm_name" x-model="confirmation" autocomplete="off"
                                                       class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none">
                                                @error('confirm_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                                                <x-button type="submit" variant="danger" x-bind:disabled="confirmation !== 'Bye bye {{ $monitor->name }}'">
                                                    Delete monitor
                                                </x-button>
                                            </div>
                                        </form>
                                    </x-modal>
                                </div>
                            </template>
                        @else
                            <a href="{{ route('organizations.monitors.show', [$organization, $monitor]) }}" class="text-sm text-gray-500 hover:underline">View</a>
                        @endif
                    </x-table.cell>
                </x-table.row>
            @empty
                <x-table.empty :colspan="6">No monitors yet.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>

    <div class="mt-4">
        <x-pagination :paginator="$monitors" :livewire="false" />
    </div>
@endsection
