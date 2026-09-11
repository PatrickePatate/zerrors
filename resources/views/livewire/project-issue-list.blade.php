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
                <x-issue-row :issue="$issue" :organization="$organization" :project="$project" :members="$members" />
            @empty
                    <x-table.empty :colspan="6">No issues match these filters.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>

    <div class="mt-4">
        <x-pagination :paginator="$issues" />
    </div>
</div>
