<x-modal max-width="lg">
    <x-slot:trigger>
        <x-button variant="secondary" type="button">
            <x-lucide-share-2 class="h-4 w-4" />
            Share
        </x-button>
    </x-slot:trigger>

    <h2 class="text-base font-semibold text-gray-900">Share this issue</h2>
    <p class="mt-1 text-sm text-gray-500">Anyone with a share link can view this issue without signing in.</p>

    <form wire:submit="createLink" class="mt-4 space-y-4">
        <x-form.select label="Visibility" id="visibility" wire:model.live="visibility"
                        :options="['public' => 'Public', 'password' => 'Password', 'temporary' => 'Temporary']"
                        :selected="$visibility" :error="$errors->first('visibility')" />

        @if($visibility === 'password')
            <x-form.text-input label="Password" name="password" type="text" wire:model="password" placeholder="Choose a password" :error="$errors->first('password')" />
        @endif

        @if($visibility === 'temporary')
            <x-form.select label="Expires after" id="duration" wire:model="duration"
                            :options="['3600' => '1 hour', '86400' => '1 day', '604800' => '7 days', '2592000' => '30 days']"
                            :selected="$duration" :error="$errors->first('duration')" />
        @endif

        <x-button type="submit" class="w-full justify-center">Create link</x-button>
    </form>

    @if($links->isNotEmpty())
        <div class="mt-6 space-y-2 border-t border-gray-200 pt-4">
            <h3 class="text-sm font-medium text-gray-700">Existing links</h3>
            @foreach($links as $shareLink)
                @php
                    $url = route('share.show', $shareLink->token);
                    $isDead = $shareLink->isRevoked() || $shareLink->isExpired();
                @endphp
                <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <x-badge :color="$isDead ? 'gray' : 'green'">{{ ucfirst($shareLink->visibility) }}</x-badge>
                            @if($shareLink->isRevoked())
                                <x-badge color="red">Revoked</x-badge>
                            @elseif($shareLink->isExpired())
                                <x-badge color="amber">Expired</x-badge>
                            @elseif($shareLink->expires_at)
                                <span class="text-xs text-gray-400">Expires {{ $shareLink->expires_at->diffForHumans() }}</span>
                            @endif
                        </div>
                        @unless($isDead)
                            <p class="mt-1 truncate text-xs text-gray-500">{{ $url }}</p>
                        @endunless
                    </div>
                    @unless($isDead)
                        <div class="flex shrink-0 items-center gap-1.5">
                            <button type="button"
                                    x-data="{ copied: false }"
                                    @click="$clipboard(@js($url)); copied = true; setTimeout(() => copied = false, 1500)"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50">
                                <x-lucide-copy-check x-cloak x-show="copied" class="h-3.5 w-3.5 text-emerald-600" />
                                <x-lucide-copy x-show="!copied" class="h-3.5 w-3.5" />
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </button>
                            <button type="button" wire:click="revoke({{ $shareLink->id }})" wire:confirm="Revoke this share link?" class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-600 hover:bg-red-50 hover:text-red-700">
                                Revoke
                            </button>
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif
</x-modal>
