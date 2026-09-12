@props(['issue', 'organization', 'members', 'project' => null, 'showProject' => false])

@php
    $project ??= $issue->project;
    $levelColors = ['error' => 'red', 'warning' => 'amber', 'fatal' => 'red', 'info' => 'blue'];
    $statusColors = ['unresolved' => 'red', 'resolved' => 'green', 'ignored' => 'gray'];
@endphp

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
    @if($showProject)
        <x-table.cell class="text-gray-600">
            <span class="inline-flex items-center gap-1">{{ $project->platform->icon() }} {{ $project->name }}</span>
        </x-table.cell>
    @endif
    <x-table.cell><x-badge :color="$levelColors[$issue->level] ?? 'gray'">{{ $issue->level }}</x-badge></x-table.cell>
    <x-table.cell class="text-gray-600">{{ $issue->times_seen }}</x-table.cell>
    <x-table.cell class="text-gray-500">{{ $issue->last_seen_at?->diffForHumans() }}</x-table.cell>
    @unless($showProject)
        <x-table.cell class="text-gray-500">{{ $issue->assignee?->name ?? '—' }}</x-table.cell>
    @endunless

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
            <x-dropdown-link :href="route('organizations.issues.show', [$organization, $project, $issue])" @click="menuOpen = false">
                <x-lucide-eye class="h-3.5 w-3.5" /> View
            </x-dropdown-link>

            <div class="my-1 border-t border-gray-100"></div>

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
        </div>
    </template>
</x-table.row>
