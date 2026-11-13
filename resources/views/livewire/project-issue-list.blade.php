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
            <div class="w-36">
                <x-form.select label="Handled" id="handled" wire:model.live="handled"
                                :options="collect(['handled' => 'Handled', 'unhandled' => 'Unhandled'])->prepend('Any', '')"
                                :selected="$handled" />
            </div>
            <div wire:loading class="text-sm text-gray-400">Filtering&hellip;</div>
            @if($search !== '' || $level !== '' || $status !== '' || $assigned !== '' || $handled !== '')
                <button type="button" wire:click="clearFilters" class="text-sm text-gray-500 hover:underline">Clear</button>
            @endif
        </div>
    </x-card>

    @if(count($selected) > 0 && auth()->user()?->can('write', $organization))
        @php($itemClass = 'flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100')
        <x-card class="mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-700">{{ count($selected) }} selected</span>
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button type="button" class="!py-1.5"><x-lucide-check class="h-4 w-4" /> Resolve</x-button>
                    </x-slot:trigger>
                    <button type="button" wire:click="bulkResolve('now')" class="{{ $itemClass }}">Resolve</button>
                    <button type="button" wire:click="bulkResolve('next')" class="{{ $itemClass }}">Resolve in next release</button>
                    @if($hasReleases)
                        <button type="button" wire:click="bulkResolve('current')" class="{{ $itemClass }}">Resolve in current release</button>
                    @endif
                </x-dropdown>
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button type="button" variant="secondary" class="!py-1.5"><x-lucide-eye-off class="h-4 w-4" /> Ignore&hellip;</x-button>
                    </x-slot:trigger>
                    @foreach(\App\Support\Fault\IssueWorkflow::IGNORE_OPTIONS as $key => $label)
                        <button type="button" wire:click="bulkIgnore('{{ $key }}')" class="{{ $itemClass }}">{{ $label }}</button>
                    @endforeach
                </x-dropdown>
                <x-button type="button" variant="secondary" class="!py-1.5" wire:click="bulkUnresolve"><x-lucide-rotate-ccw class="h-4 w-4" /> Unresolve</x-button>
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button type="button" variant="secondary" class="!py-1.5"><x-lucide-user class="h-4 w-4" /> Assign to&hellip;</x-button>
                    </x-slot:trigger>
                    <button type="button" wire:click="bulkAssign(null)" class="{{ $itemClass }}">Unassign</button>
                    @foreach($members as $member)
                        <button type="button" wire:click="bulkAssign({{ $member->id }})" class="{{ $itemClass }}">{{ $member->name }}</button>
                    @endforeach
                </x-dropdown>
                @if(count($selected) > 1)
                    <x-button type="button" variant="secondary" class="!py-1.5" wire:click="bulkMerge" wire:confirm="Merge the selected issues into the one with the most events? This cannot be undone.">
                        <x-lucide-merge class="h-4 w-4" /> Merge
                    </x-button>
                @endif
                <button type="button" wire:click="clearSelection" class="text-sm text-gray-500 hover:underline">Clear selection</button>
            </div>
        </x-card>
    @endif

    <x-table>
        <x-table.head>
            <x-table.column>
                <input type="checkbox" wire:click="toggleSelectPage" aria-label="Select all on this page"
                       @checked($issues->count() > 0 && collect($issues->items())->pluck('id')->diff($selected)->isEmpty())
                       class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/10">
            </x-table.column>
            <x-table.column>Status</x-table.column>
            <x-table.column>Issue</x-table.column>
            <x-table.column>Level</x-table.column>
            <x-table.column>Events</x-table.column>
            <x-table.column>Last seen</x-table.column>
            <x-table.column>Users</x-table.column>
            <x-table.column>Assignee</x-table.column>
        </x-table.head>
        <x-table.body>
            @forelse($issues as $issue)
                <x-issue-row :issue="$issue" :organization="$organization" :project="$project" :members="$members" :selectable="true" />
            @empty
                    <x-table.empty :colspan="8">No issues match these filters.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>

    <div class="mt-4">
        <x-pagination :paginator="$issues" />
    </div>
</div>
