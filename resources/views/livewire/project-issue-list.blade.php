@php
    $levelColors = ['error' => 'red', 'warning' => 'amber', 'fatal' => 'red', 'info' => 'blue'];
    $statusColors = ['unresolved' => 'red', 'resolved' => 'green', 'ignored' => 'gray'];
@endphp

<div>
    <x-card class="mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <div class="max-w-xs flex-1">
                <label for="q" class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                <input type="text" id="q" wire:model.live.debounce.300ms="search" placeholder="Title, culprit, type&hellip;"
                       class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
            </div>
            <div class="w-36">
                <label for="level" class="mb-1 block text-sm font-medium text-gray-700">Level</label>
                <select id="level" wire:model.live="level" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                    <option value="">Any</option>
                    @foreach(['error', 'warning', 'fatal', 'info'] as $option)
                        <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-36">
                <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select id="status" wire:model.live="status" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                    <option value="">Any</option>
                    @foreach(['unresolved', 'resolved', 'ignored'] as $option)
                        <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label for="assigned" class="mb-1 block text-sm font-medium text-gray-700">Assigned</label>
                <select id="assigned" wire:model.live="assigned" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                    <option value="">Anyone</option>
                    <option value="me">Me</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            </div>
            <div wire:loading class="text-sm text-gray-400">Filtering&hellip;</div>
            @if($search !== '' || $level !== '' || $status !== '' || $assigned !== '')
                <button type="button" wire:click="clearFilters" class="text-sm text-gray-500 hover:underline">Clear</button>
            @endif
        </div>
    </x-card>

    <x-card :padding="false">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3 font-medium">Issue</th>
                    <th class="px-5 py-3 font-medium">Level</th>
                    <th class="px-5 py-3 font-medium">Events</th>
                    <th class="px-5 py-3 font-medium">Last seen</th>
                    <th class="px-5 py-3 font-medium">Assignee</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($issues as $issue)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('organizations.issues.show', [$organization, $project, $issue]) }}" class="font-medium text-gray-900 hover:underline">{{ $issue->title }}</a>
                            <p class="text-xs text-gray-400">{{ $issue->culprit }}</p>
                        </td>
                        <td class="px-5 py-3"><x-badge :color="$levelColors[$issue->level] ?? 'gray'">{{ $issue->level }}</x-badge></td>
                        <td class="px-5 py-3 text-gray-600">{{ $issue->times_seen }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $issue->last_seen_at?->diffForHumans() }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $issue->assignee?->name ?? '—' }}</td>
                        <td class="px-5 py-3"><x-badge :color="$statusColors[$issue->status] ?? 'gray'">{{ $issue->status }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-6 text-sm text-gray-400">No issues match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $issues->links() }}</div>
</div>
