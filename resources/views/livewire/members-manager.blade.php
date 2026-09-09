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
                    <x-input label="Email" name="email" type="email" wire:model="email" :error="$errors->first('email')" required autofocus />
                    <div>
                        <label for="role" class="mb-1 block text-sm font-medium text-gray-700">Role</label>
                        <select id="role" wire:model="role" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
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

    <x-card :padding="false" class="mb-6">
        <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-medium text-gray-700">Members</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-2 font-medium">Name</th>
                    <th class="px-5 py-2 font-medium">Email</th>
                    <th class="px-5 py-2 font-medium">Role</th>
                    <th class="px-5 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($this->members as $member)
                    @php
                        $canEditRole = in_array($this->myRole, ['owner', 'admin'], true)
                            && ($this->myRole === 'owner' || $member->pivot->role !== 'owner');
                    @endphp
                    <tr class="hover:bg-gray-50" wire:key="member-{{ $member->id }}">
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $member->name }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $member->email }}</td>
                        <td class="px-5 py-3">
                            @if($canEditRole)
                                <select wire:change="updateRole({{ $member->id }}, $event.target.value)"
                                        class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                                    @if($this->myRole === 'owner')
                                        <option value="owner" @selected($member->pivot->role === 'owner')>owner</option>
                                    @endif
                                    <option value="admin" @selected($member->pivot->role === 'admin')>admin</option>
                                    <option value="member" @selected($member->pivot->role === 'member')>member</option>
                                </select>
                            @else
                                <x-badge color="{{ $member->pivot->role === 'owner' ? 'indigo' : 'gray' }}">{{ $member->pivot->role }}</x-badge>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if(in_array($this->myRole, ['owner', 'admin']) && $member->id !== auth()->id())
                                <button type="button" wire:click="removeMember({{ $member->id }})"
                                        wire:confirm="Remove {{ $member->name }} from this organization?"
                                        class="text-sm text-red-600 hover:underline">Remove</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if($removalError)
            <p class="border-t border-gray-100 px-5 py-3 text-sm text-red-600">{{ $removalError }}</p>
        @endif
    </x-card>

    @if(in_array($this->myRole, ['owner', 'admin']) && $this->invites->isNotEmpty())
        <x-card :padding="false">
            <h2 class="border-b border-gray-100 px-5 py-3 text-sm font-medium text-gray-700">Pending invites</h2>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                        <th class="px-5 py-2 font-medium">Email</th>
                        <th class="px-5 py-2 font-medium">Role</th>
                        <th class="px-5 py-2 font-medium">Expires</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($this->invites as $invite)
                        <tr class="hover:bg-gray-50" wire:key="invite-{{ $invite->id }}">
                            <td class="px-5 py-3 text-gray-700">{{ $invite->email }}</td>
                            <td class="px-5 py-3"><x-badge>{{ $invite->role }}</x-badge></td>
                            <td class="px-5 py-3 text-gray-400">{{ $invite->isExpired() ? 'expired' : $invite->expires_at?->diffForHumans() }}</td>
                            <td class="px-5 py-3 text-right">
                                <button type="button" wire:click="revokeInvite({{ $invite->id }})" class="text-sm text-red-600 hover:underline">Revoke</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endif
</div>
