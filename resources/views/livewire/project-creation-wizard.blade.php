<div>
    <x-modal entangle="showModal">
        <x-slot:trigger>
            <x-button type="button">
                <x-lucide-plus class="h-4 w-4" />
                New project
            </x-button>
        </x-slot:trigger>

        @if($step === 1)
            <h2 class="mb-4 text-sm font-medium text-gray-700">New project</h2>
            <form wire:submit="createProject" class="space-y-4">
                <x-form.text-input label="Name" name="name" placeholder="Project name" wire:model="name" :error="$errors->first('name')" required autofocus />
                <x-form.select label="Type" id="platform" wire:model="platform"
                                :options="collect(\App\Enums\FaultPlatform::cases())->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()])"
                                :selected="$platform" :error="$errors->first('platform')" />
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                    <x-button type="submit" wire:loading.attr="disabled">Create</x-button>
                </div>
            </form>
        @else
            <h2 class="mb-1 text-sm font-medium text-gray-700">Set up {{ $project->name }}</h2>
            <p class="mb-4 text-sm text-gray-500">Follow these steps to start sending errors from your {{ $project->platform->label() }} app.</p>

            <ol class="mb-4 space-y-3">
                @foreach($this->tutorialSteps() as $index => $tutorialStep)
                    <li>
                        <p class="mb-1 text-sm text-gray-700">
                            <span class="font-medium">{{ $index + 1 }}.</span> {{ $tutorialStep['description'] }}
                        </p>
                        @if($tutorialStep['code'])
                            <x-credential :value="$tutorialStep['code']" class="w-full" />
                        @endif
                    </li>
                @endforeach
            </ol>

            <div class="flex justify-end gap-2">
                <x-button type="button" wire:click="finish">Done</x-button>
            </div>
        @endif
    </x-modal>
</div>
