@php
    $levelColors = ['error' => 'red', 'warning' => 'amber', 'fatal' => 'red', 'info' => 'blue'];
    $statusColors = ['unresolved' => 'red', 'resolved' => 'green', 'ignored' => 'gray'];
@endphp

<div>
    <x-card class="mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <div class="max-w-xs flex-1">
                <x-form.text-input label="Search" name="q" wire:model.live.debounce.300ms="search" placeholder="Title, culprit, type&hellip;" />
            </div>
            <div class="w-36">
                <x-form.select label="Level" id="level" wire:model.live="level"
                                :options="collect(['error', 'warning', 'fatal', 'info'])->mapWithKeys(fn ($option) => [$option => ucfirst($option)])->prepend('Any', '')"
                                :selected="$level" />
            </div>
            <div class="w-36">
                <x-form.select label="Status" id="status" wire:model.live="status"
                                :options="collect(['unresolved', 'resolved', 'ignored'])->mapWithKeys(fn ($option) => [$option => ucfirst($option)])->prepend('Any', '')"
                                :selected="$status" />
            </div>
            <div class="w-40">
                <x-form.select label="Assigned" id="assigned" wire:model.live="assigned"
                                :options="['' => 'Anyone', 'me' => 'Me', 'unassigned' => 'Unassigned']"
                                :selected="$assigned" />
            </div>
            <div wire:loading class="text-sm text-gray-400">Filtering&hellip;</div>
            @if($search !== '' || $level !== '' || $status !== '' || $assigned !== '')
                <button type="button" wire:click="clearFilters" class="text-sm text-gray-500 hover:underline">Clear</button>
            @endif
        </div>
    </x-card>

    <x-table>
        <x-table.head>
            <x-table.column>Status</x-table.column>
            <x-table.column>Issue</x-table.column>
            <x-table.column>Level</x-table.column>
            <x-table.column>Events</x-table.column>
            <x-table.column>Last seen</x-table.column>
            <x-table.column>Assignee</x-table.column>
        </x-table.head>
        <x-table.body>
            @forelse($issues as $issue)
                    <x-table.row
                        :muted="$issue->status !== 'unresolved'"
                        x-data="{ menuOpen: false, x: 0, y: 0 }"
                        @contextmenu.prevent="$dispatch('issue-context-menu-open', {{ $issue->id }}); x = $event.clientX; y = $event.clientY; menuOpen = true"
                        @issue-context-menu-open.window="if ($event.detail !== {{ $issue->id }}) menuOpen = false"
                        @click.outside="menuOpen = false"
                        @keydown.escape.window="menuOpen = false"
                        ::class="menuOpen && 'bg-gray-50'"
                    >
                        <x-table.cell><x-badge :color="$statusColors[$issue->status] ?? 'gray'">{{ ucfirst($issue->status) }}</x-badge></x-table.cell>
                        <x-table.cell>
                            <a href="{{ route('organizations.issues.show', [$organization, $project, $issue]) }}" class="font-medium text-gray-900 hover:underline">{{ $issue->title }}</a>
                            <p class="text-xs text-gray-400">{{ $issue->culprit }}</p>
                        </x-table.cell>
                        <x-table.cell><x-badge :color="$levelColors[$issue->level] ?? 'gray'">{{ $issue->level }}</x-badge></x-table.cell>
                        <x-table.cell class="text-gray-600">{{ $issue->times_seen }}</x-table.cell>
                        <x-table.cell class="text-gray-500">{{ $issue->last_seen_at?->diffForHumans() }}</x-table.cell>
                        <x-table.cell class="text-gray-500">{{ $issue->assignee?->name ?? '—' }}</x-table.cell>
                        <template x-teleport="body">
                            <div
                                x-show="menuOpen"
                                x-cloak
                                x-transition:enter="ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                :style="`top:${y}px; left:${x}px`"
                                class="fixed z-50 w-52 rounded-md border border-gray-200/70 bg-white p-1 text-neutral-700 shadow-lg"
                            >
                                @if($issue->status !== 'resolved')
                                    <button type="button" wire:click="updateIssueStatus({{ $issue->id }}, 'resolved')" @click="menuOpen = false"
                                            class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100">
                                        <x-lucide-check class="h-3.5 w-3.5" />
                                        Resolve
                                    </button>
                                @endif
                                @if($issue->status !== 'ignored')
                                    <button type="button" wire:click="updateIssueStatus({{ $issue->id }}, 'ignored')" @click="menuOpen = false"
                                            class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100">
                                        <x-lucide-eye-off class="h-3.5 w-3.5" />
                                        Ignore
                                    </button>
                                @endif
                                @if($issue->status !== 'unresolved')
                                    <button type="button" wire:click="updateIssueStatus({{ $issue->id }}, 'unresolved')" @click="menuOpen = false"
                                            class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100">
                                        <x-lucide-rotate-ccw class="h-3.5 w-3.5" />
                                        Mark as unresolved
                                    </button>
                                @endif

                                <div class="my-1 border-t border-gray-100"></div>

                                <p class="px-2.5 pt-1 pb-0.5 text-xs font-medium tracking-wide text-gray-400 uppercase">Assign to</p>
                                @if($issue->assigned_to_user_id)
                                    <button type="button" wire:click="assignIssue({{ $issue->id }}, null)" @click="menuOpen = false"
                                            class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100">
                                        <x-lucide-circle-slash class="h-3.5 w-3.5" />
                                        Unassign
                                    </button>
                                @endif
                                <div class="max-h-40 overflow-y-auto">
                                    @foreach($members as $member)
                                        <button type="button" wire:click="assignIssue({{ $issue->id }}, {{ $member->id }})" @click="menuOpen = false"
                                                class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100">
                                            <img src="{{ $member->avatarUrl() }}" alt="{{ $member->name }}" class="h-4 w-4 shrink-0 rounded-full object-cover">
                                            <span class="min-w-0 flex-1 truncate">{{ $member->name }}</span>
                                            @if($issue->assigned_to_user_id === $member->id)
                                                <x-lucide-check class="h-3.5 w-3.5 shrink-0" />
                                            @endif
                                        </button>
                                    @endforeach
                                </div>

                                <div class="my-1 border-t border-gray-100"></div>

                                <a href="{{ route('organizations.issues.show', [$organization, $project, $issue]) }}"
                                   class="flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100">
                                    <x-lucide-external-link class="h-3.5 w-3.5" />
                                    View issue
                                </a>
                            </div>
                        </template>
                    </x-table.row>
            @empty
                    <x-table.empty :colspan="6">No issues match these filters.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>

    <div class="mt-4">
        <x-pagination :paginator="$issues" />
    </div>
</div>
