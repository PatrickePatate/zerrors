<div>
    @php
        $category = \App\Enums\TriageCategory::tryFrom((string) $issue->triage_category);
        $priority = \App\Enums\TriagePriority::tryFrom((string) $issue->triage_priority);
    @endphp

    @if($canTriage || $issue->triaged_at || $issue->triage_error || $duplicates->isNotEmpty())
        <x-card>
            @if($canTriage || $issue->triaged_at || $issue->triage_error)
                <h2 class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                    <x-lucide-sparkles class="h-4 w-4 text-indigo-500" />
                    AI triage
                </h2>

                @if($issue->triaged_at)
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @if($category)
                            <x-badge :color="$category->color()">{{ $category->label() }}</x-badge>
                        @endif
                        @if($priority)
                            <x-badge :color="$priority->color()">{{ $priority->label() }} priority</x-badge>
                        @endif
                        @unless($category || $priority)
                            <span class="text-sm text-gray-500">The model returned no usable label.</span>
                        @endunless
                    </div>
                    <p class="mt-1 text-xs text-gray-400">{{ $issue->triage_model }} · {{ \Illuminate\Support\Carbon::parse($issue->triaged_at)->diffForHumans() }}</p>
                @elseif($issue->triage_error)
                    <p class="mt-2 text-sm text-red-600">Triage failed: {{ $issue->triage_error }}</p>
                    <p class="text-xs text-gray-400">{{ $issue->triage_model }}</p>
                @else
                    <p class="mt-2 text-sm text-gray-500">Not triaged yet.</p>
                @endif

                @if($canTriage && auth()->user()?->can('write', $issue->project->organization))
                    <button type="button" wire:click="rerun" wire:loading.attr="disabled" class="mt-2 text-sm text-gray-600 hover:underline">
                        Re-run triage
                    </button>
                @endif
            @endif

            @if($duplicates->isNotEmpty())
                <h3 class="@if($canTriage || $issue->triaged_at || $issue->triage_error) mt-4 @endif text-sm font-medium text-gray-700">Possible duplicates</h3>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach($duplicates as $duplicate)
                        <li>
                            <a href="{{ route('organizations.issues.show', [$organization, $project, $duplicate]) }}" class="text-gray-900 hover:underline">{{ $duplicate->title }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif
</div>
