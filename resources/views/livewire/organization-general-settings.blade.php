<div>
    <x-card class="mb-6">
        <h2 class="mb-3 text-sm font-medium text-gray-700">General</h2>

        @if($saved)
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</div>
        @endif

        <form wire:submit="save" class="space-y-4">
            <div class="flex items-end gap-3">
                <div class="max-w-xs flex-1">
                    <x-form.text-input label="Name" name="name" wire:model="name" :error="$errors->first('name')" required />
                </div>
            </div>
            <x-form.checkbox wire:model="alertsEnabled">Email organization members when a new issue appears or a resolved one regresses</x-form.checkbox>
            <p class="text-xs text-gray-400">
                Slack, Telegram, and per-project alert email are configured per project — see a project's "Edit project" &rarr; Notifications tab.
            </p>
            <x-form.checkbox wire:model="require2fa">Require two-factor authentication for all members</x-form.checkbox>
            <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
        </form>
    </x-card>
</div>
