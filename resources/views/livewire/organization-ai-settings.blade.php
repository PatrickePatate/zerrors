<div>
    <x-card class="mb-6">
        <h2 class="mb-1 text-sm font-medium text-gray-700">AI assistant</h2>
        <p class="mb-3 text-sm text-gray-500">
            Connect an AI provider to get a suggested cause and fix for issues, right from the issue page.
            Your API key is encrypted at rest and never shown again after saving.
        </p>

        @if($saved)
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</div>
        @endif

        <form wire:submit="save" class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <x-form.select label="Provider" id="aiProvider" wire:model="aiProvider" required
                                :options="['' => 'Choose one', ...\App\Models\Organization::AI_PROVIDERS]"
                                :selected="$aiProvider" />
            </div>
            <div class="max-w-xs flex-1">
                <x-form.text-input label="API key" type="password" name="aiApiKey" wire:model="aiApiKey" autocomplete="off"
                          placeholder="{{ $organization->hasAiConfigured() ? 'Configured — leave blank to keep it' : 'sk-...' }}"
                          :error="$errors->first('aiApiKey')" />
            </div>
            <div class="w-48">
                <x-form.text-input label="Model (optional)" name="aiModel" wire:model="aiModel" placeholder="Provider default" />
            </div>
            <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
        </form>

        @if($organization->hasAiConfigured())
            <button type="button" wire:click="disconnect" wire:confirm="Disconnect the AI assistant?" class="mt-3 text-sm text-red-600 hover:underline">
                Disconnect AI assistant
            </button>
        @endif
    </x-card>
</div>
