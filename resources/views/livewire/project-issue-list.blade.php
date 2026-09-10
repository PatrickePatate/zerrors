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
