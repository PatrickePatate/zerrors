<div class="space-y-6">
    <x-card>
        <h2 class="mb-3 text-sm font-medium text-gray-700">Settings</h2>

        @if($saved)
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</div>
        @endif

        <form wire:submit="save" class="space-y-4">
            <x-form.checkbox wire:model="isPublic">Make the status page public</x-form.checkbox>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.text-input label="Slug" name="slug" wire:model="slug" :error="$errors->first('slug')" required />
                <x-form.text-input label="Title" name="title" wire:model="title" :error="$errors->first('title')" required />
            </div>
            @if($page)
                <p class="text-xs text-gray-400">
                    Public URL: <a href="{{ route('status.show', $page->slug) }}" target="_blank" class="underline">{{ route('status.show', $page->slug) }}</a>
                </p>
            @endif

            <x-form.textarea label="Description" name="description" wire:model="description" rows="2" :error="$errors->first('description')" />

            <div>
                <h3 class="mb-1 text-sm font-medium text-gray-700">Monitors</h3>
                <p class="mb-2 text-xs text-gray-400">Only checked monitors are shown, under the public name you choose. URLs are never exposed.</p>
                <div class="space-y-2">
                    @forelse($monitorRows as $index => $row)
                        <div wire:key="row-{{ $row['id'] }}" class="flex items-center gap-2">
                            <input type="checkbox" wire:model="monitorRows.{{ $index }}.selected" class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/10" aria-label="Show {{ $row['original'] }}">
                            <div class="flex-1">
                                <x-form.text-input name="monitor-name-{{ $row['id'] }}" wire:model="monitorRows.{{ $index }}.name" placeholder="{{ $row['original'] }}" />
                            </div>
                            <button type="button" wire:click="moveUp({{ $index }})" class="rounded p-1.5 text-gray-400 hover:bg-gray-100" aria-label="Move up"><x-lucide-arrow-up class="h-4 w-4" /></button>
                            <button type="button" wire:click="moveDown({{ $index }})" class="rounded p-1.5 text-gray-400 hover:bg-gray-100" aria-label="Move down"><x-lucide-arrow-down class="h-4 w-4" /></button>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No monitors yet.</p>
                    @endforelse
                </div>
            </div>

            <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
        </form>
    </x-card>

    <x-card>
        <h2 class="mb-3 text-sm font-medium text-gray-700">Incidents</h2>

        @if($page)
            <form wire:submit="createIncident" class="mb-5 space-y-3">
                <x-form.text-input label="Title" name="incidentTitle" wire:model="incidentTitle" :error="$errors->first('incidentTitle')" />
                <x-form.textarea label="Message" name="incidentBody" wire:model="incidentBody" rows="2" :error="$errors->first('incidentBody')" />
                <div>
                    <label for="incidentState" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                    <select id="incidentState" wire:model="incidentState" class="h-10 rounded-md border border-gray-300 bg-white px-3 text-sm">
                        @foreach($states as $state)
                            <option value="{{ $state->value }}">{{ $state->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit">Post incident</x-button>
            </form>

            <div class="space-y-4">
                @forelse($incidents as $incident)
                    <div wire:key="incident-{{ $incident->id }}" class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-gray-900">
                                {{ $incident->title }}
                                @if($incident->auto)<x-badge color="gray">Auto</x-badge>@endif
                                <x-badge :color="$incident->isResolved() ? 'green' : 'red'">{{ $incident->isResolved() ? 'Resolved' : 'Active' }}</x-badge>
                            </div>
                            @unless($incident->isResolved())
                                <x-button type="button" variant="secondary" wire:click="resolveIncident({{ $incident->id }})">Resolve</x-button>
                            @endunless
                        </div>
                        <ul class="mt-2 space-y-1 text-sm text-gray-600">
                            @foreach($incident->updates as $update)
                                <li><span class="font-medium">{{ $update->status->label() }}</span> &middot; {{ $update->body }} <span class="text-xs text-gray-400">{{ $update->created_at->diffForHumans() }}</span></li>
                            @endforeach
                        </ul>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input type="text" wire:model="updateBodies.{{ $incident->id }}" placeholder="Post an update…" class="h-10 flex-1 rounded-md border border-gray-300 px-3 text-sm">
                            <select wire:model="updateStates.{{ $incident->id }}" class="h-10 rounded-md border border-gray-300 bg-white px-3 text-sm">
                                @foreach($states as $state)
                                    <option value="{{ $state->value }}">{{ $state->label() }}</option>
                                @endforeach
                            </select>
                            <x-button type="button" wire:click="postUpdate({{ $incident->id }})">Post</x-button>
                        </div>
                        @error('updateBodies.'.$incident->id)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No incidents.</p>
                @endforelse
            </div>
        @else
            <p class="text-sm text-gray-500">Save the status page first to post incidents.</p>
        @endif
    </x-card>
</div>
