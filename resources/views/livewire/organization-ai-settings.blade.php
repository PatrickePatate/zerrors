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
            <div class="w-48">
                <x-form.text-input label="Deep analysis model (optional)" name="aiDeepModel" wire:model="aiDeepModel" placeholder="Same as above" />
            </div>
            <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
        </form>
        <p class="mt-2 text-xs text-gray-500">
            The deeper analysis reuses the model above unless you set a different (e.g. more capable) one just for it.
        </p>

        <div class="mt-6 border-t border-gray-100 pt-4">
            <h3 class="mb-1 text-sm font-medium text-gray-700">Automatic triage</h3>
            <p class="mb-3 text-sm text-gray-500">
                Classify each new issue (category and priority) in the background through OpenRouter.
            </p>
            <form wire:submit="save" class="flex flex-wrap items-end gap-3">
                <div class="w-full">
                    <x-form.checkbox wire:model="aiTriageEnabled">Triage new issues automatically</x-form.checkbox>
                </div>
                <div class="w-64">
                    <x-form.text-input label="Triage model" name="aiTriageModel" wire:model="aiTriageModel"
                                       :error="$errors->first('aiTriageModel')" />
                </div>
                @if($aiProvider !== 'openrouter')
                    <div class="max-w-xs flex-1">
                        <x-form.text-input label="OpenRouter API key" type="password" name="aiTriageApiKey" wire:model="aiTriageApiKey" autocomplete="off"
                                           placeholder="{{ $organization->ai_triage_api_key ? 'Configured — leave blank to keep it' : 'sk-or-...' }}"
                                           :error="$errors->first('aiTriageApiKey')" />
                    </div>
                @endif
                <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
            </form>
            <p class="mt-2 text-xs text-gray-500">
                The model ID is sent to OpenRouter as-is; if it is wrong, triage fails and the error is shown on the issue.
                @if($aiProvider === 'openrouter')
                    Your OpenRouter key above is used.
                @endif
            </p>
        </div>

        @if($organization->hasAiConfigured())
            <button type="button" wire:click="disconnect" wire:confirm="Disconnect the AI assistant?" class="mt-3 text-sm text-red-600 hover:underline">
                Disconnect AI assistant
            </button>
        @endif
    </x-card>
</div>
