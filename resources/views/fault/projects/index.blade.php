@extends('layouts.app')

@section('title', $organization->name.' · Zerrors')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Projects</h1>

        <x-modal :open-on-error="$errors->has('name') || $errors->has('platform') || request()->boolean('new')">
            <x-slot:trigger>
                <x-button type="button">
                    <x-lucide-plus class="h-4 w-4" />
                    New project
                </x-button>
            </x-slot:trigger>

            <h2 class="mb-4 text-sm font-medium text-gray-700">New project</h2>
            <form method="POST" action="{{ route('organizations.projects.store', $organization) }}" class="space-y-4">
                @csrf
                <x-form.text-input label="Name" name="name" placeholder="Project name" value="{{ old('name') }}" :error="$errors->first('name')" required autofocus />
                <x-form.select label="Type" id="platform" name="platform"
                                :options="collect(\App\Enums\FaultPlatform::cases())->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()])"
                                :selected="old('platform')" />
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                    <x-button type="submit">Create</x-button>
                </div>
            </form>
        </x-modal>
    </div>

    <x-table>
        <x-table.head>
            <x-table.column>Project</x-table.column>
            <x-table.column>Issues</x-table.column>
            <x-table.column>Unresolved</x-table.column>
            <x-table.column>DSN</x-table.column>
            <x-table.column></x-table.column>
        </x-table.head>
        <x-table.body>
            @forelse($projects as $project)
                {{--
                    Actions menu is opened either by clicking the trigger button or by
                    right-clicking anywhere on the row. Both need to share one Alpine
                    scope, so it lives directly on the <tr> rather than inside a nested
                    <x-dropdown> — a wrapping div around the whole row would break table
                    layout. The panel itself is teleported to <body> and positioned with
                    fixed coordinates so it isn't clipped by the table's overflow wrapper.
                --}}
                <x-table.row
                    x-data="{
                        menuOpen: false,
                        menuStyle: '',
                        openMenuAt(x, y) {
                            this.menuStyle = `top:${y}px; left:${x}px;`;
                            this.menuOpen = true;
                        },
                        openMenuBelow(el) {
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
                        <div class="flex items-center gap-2">
                            <x-tooltip :message="$project->platform->label()">{{ $project->platform->icon(size: '6') }}</x-tooltip>
                            <a href="{{ route('organizations.projects.show', [$organization, $project]) }}" class="font-semibold text-gray-900 hover:underline">{{ $project->name }}</a>
                        </div>
                    </x-table.cell>
                    <x-table.cell class="text-gray-600">{{ $project->issues_count }}</x-table.cell>
                    <x-table.cell>
                        @if($project->unresolved_issues_count > 0)
                            <x-badge color="red">{{ $project->unresolved_issues_count }}</x-badge>
                        @else
                            <span class="text-gray-400">0</span>
                        @endif
                    </x-table.cell>
                    <x-table.cell><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $project->dsn() }}</code></x-table.cell>
                    <x-table.cell class="text-right">
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
                                <x-modal max-width="sm" :open-on-error="$errors->has('confirm_name') && old('project_id') == $project->id">
                                    <x-slot:trigger>
                                        <x-dropdown-link tag="button" type="button" @click="menuOpen = false" class="text-red-600 hover:bg-red-50">
                                            <x-lucide-trash-2 class="h-4 w-4" /> Delete project
                                        </x-dropdown-link>
                                    </x-slot:trigger>

                                    <h2 class="mb-1 flex items-center gap-1.5 text-sm font-medium text-red-700">
                                        <x-lucide-triangle-alert class="h-4 w-4" /> Delete project
                                    </h2>
                                    <p class="mb-3 text-sm text-gray-500">
                                        Deletes <strong>{{ $project->name }}</strong> and everything in it — issues, events, releases. This cannot be undone.
                                    </p>
                                    <form method="POST" action="{{ route('organizations.projects.destroy', [$organization, $project]) }}" x-data="{ confirmation: '' }" class="space-y-3">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="project_id" value="{{ $project->id }}">
                                        <div>
                                            <label for="confirm_name_{{ $project->id }}" class="mb-1 block text-sm font-medium text-gray-700">
                                                Type <span class="font-semibold">Bye bye {{ $project->name }}</span> to confirm
                                            </label>
                                            <input type="text" id="confirm_name_{{ $project->id }}" name="confirm_name" x-model="confirmation" autocomplete="off"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none">
                                            @error('confirm_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                                            <x-button type="submit" variant="danger" x-bind:disabled="confirmation !== 'Bye bye {{ $project->name }}'">
                                                Delete project
                                            </x-button>
                                        </div>
                                    </form>
                                </x-modal>
                            </div>
                        </template>
                    </x-table.cell>
                </x-table.row>
            @empty
                <x-table.empty :colspan="5">No projects yet.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>

    <div class="mt-4">
        <x-pagination :paginator="$projects" :livewire="false" />
    </div>
@endsection
