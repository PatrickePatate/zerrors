<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">{{ $this->organization->name }} &middot; Members</h1>

        @if(in_array($this->myRole, ['owner', 'admin']))
            <x-modal entangle="showInviteModal">
                <x-slot:trigger>
                    <x-button type="button">
                        <x-lucide-plus class="h-4 w-4" />
                        Invite member
                    </x-button>
                </x-slot:trigger>

                <h2 class="mb-4 text-sm font-medium text-gray-700">Invite someone</h2>
                <form wire:submit="invite" class="space-y-4">
                    <x-form.text-input label="Email" name="email" type="email" wire:model="email" :error="$errors->first('email')" required autofocus />
                    <x-form.select label="Role" id="role" wire:model="role"
                                    :options="['member' => 'Member', 'admin' => 'Admin']"
                                    :selected="$role" />
                    <div class="flex justify-end gap-2">
                        <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                        <x-button type="submit" wire:loading.attr="disabled">Send invite</x-button>
                    </div>
                </form>
            </x-modal>
        @endif
    </div>

    @if($inviteLink)
        <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
            Invite created. Share this link (email sending isn't configured):
            <code class="mt-1 block break-all rounded bg-white/60 px-2 py-1 text-xs">{{ $inviteLink }}</code>
        </div>
    @endif

    <div class="mb-6 overflow-hidden rounded-lg border border-gray-200 bg-white">
        <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-medium text-gray-700">Members</h2>
        <x-table :bordered="false">
            <x-table.head>
                <x-table.column>Name</x-table.column>
                <x-table.column>Email</x-table.column>
                <x-table.column>Role</x-table.column>
                <x-table.column></x-table.column>
            </x-table.head>
            <x-table.body>
                @foreach($this->members as $member)
                    @php
                        $canEditRole = in_array($this->myRole, ['owner', 'admin'], true)
                            && ($this->myRole === 'owner' || $member->pivot->role !== 'owner');
                    @endphp
                    <x-table.row wire:key="member-{{ $member->id }}">
                        <x-table.cell class="font-medium text-gray-900">
                            <div class="flex items-center gap-2">
                                <img src="{{ $member->avatarUrl() }}" alt="{{ $member->name }}" class="h-6 w-6 shrink-0 rounded-full object-cover">
                                {{ $member->name }}
                            </div>
                        </x-table.cell>
                        <x-table.cell class="text-gray-500">{{ $member->email }}</x-table.cell>
                        <x-table.cell>
                            @if($canEditRole)
                                <div class="w-28">
                                    <x-form.select wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                                    :options="($this->myRole === 'owner' ? ['owner' => 'owner'] : []) + ['admin' => 'admin', 'member' => 'member']"
                                                    :selected="$member->pivot->role" />
                                </div>
                            @else
                                <x-badge color="{{ $member->pivot->role === 'owner' ? 'indigo' : 'gray' }}">{{ $member->pivot->role }}</x-badge>
                            @endif
                        </x-table.cell>
                        <x-table.cell align="right">
                            @if(in_array($this->myRole, ['owner', 'admin']) && $member->id !== auth()->id())
                                <button type="button" wire:click="removeMember({{ $member->id }})"
                                        wire:confirm="Remove {{ $member->name }} from this organization?"
                                        class="text-sm text-red-600 hover:underline">Remove</button>
                            @endif
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table.body>
        </x-table>
        @if($removalError)
            <p class="border-t border-gray-100 px-5 py-3 text-sm text-red-600">{{ $removalError }}</p>
        @endif
        <div class="border-t border-gray-100 px-5 py-3">
            <x-pagination :paginator="$this->members" />
        </div>
    </div>

    @if(in_array($this->myRole, ['owner', 'admin']) && $this->invites->isNotEmpty())
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-medium text-gray-700">Pending invites</h2>
            <x-table :bordered="false">
                <x-table.head>
                    <x-table.column>Email</x-table.column>
                    <x-table.column>Role</x-table.column>
                    <x-table.column>Expires</x-table.column>
                    <x-table.column></x-table.column>
                </x-table.head>
                <x-table.body>
                    @foreach($this->invites as $invite)
                        <x-table.row wire:key="invite-{{ $invite->id }}">
                            <x-table.cell class="text-gray-700">{{ $invite->email }}</x-table.cell>
                            <x-table.cell><x-badge>{{ $invite->role }}</x-badge></x-table.cell>
                            <x-table.cell class="text-gray-400">{{ $invite->isExpired() ? 'expired' : $invite->expires_at?->diffForHumans() }}</x-table.cell>
                            <x-table.cell align="right">
                                <button type="button" wire:click="revokeInvite({{ $invite->id }})" class="text-sm text-red-600 hover:underline">Revoke</button>
                            </x-table.cell>
                        </x-table.row>
                    @endforeach
                </x-table.body>
            </x-table>
        </div>
    @endif
</div>
