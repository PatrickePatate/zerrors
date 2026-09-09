<div>
    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                    <x-lucide-sparkles class="h-4 w-4 text-indigo-500" />
                    AI analysis
                </h2>
                @if($issue->ai_analyzed_at)
                    <p class="text-xs text-gray-400">Last analyzed {{ $issue->ai_analyzed_at->diffForHumans() }}</p>
                @endif
            </div>
        </div>

        @if($error)
            <p class="mt-3 text-sm text-red-600">{{ $error }}</p>
        @endif

        @if($issue->ai_analysis)
            <div class="prose prose-sm mt-3 max-w-none prose-headings:text-sm prose-headings:font-medium prose-headings:text-gray-700">
                {!! \Illuminate\Support\Str::markdown($issue->ai_analysis) !!}
            </div>
        @elseif(! $organization->hasAiConfigured())
            <p class="mt-2 text-sm text-gray-500">
                No AI provider connected yet.
                <a href="{{ route('organizations.settings.edit', $organization) }}" class="font-medium text-gray-900 hover:underline">Connect one in settings</a>
                to get a suggested cause and fix for issues.
            </p>
        @else
            <p class="mt-2 text-sm text-gray-500">Not analyzed yet.</p>
        @endif

        @if($organization->hasAiConfigured())
            <button
                type="button"
                wire:click="analyze"
                wire:loading.attr="disabled"
                wire:target="analyze"
                class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
            >
                <span wire:loading wire:target="analyze">
                    <x-lucide-loader-2 class="h-4 w-4 animate-spin" />
                </span>
                <span wire:loading.remove wire:target="analyze">
                    {{ $issue->ai_analysis ? 'Re-analyze' : 'Analyze with AI' }}
                </span>
                <span wire:loading wire:target="analyze">Analyzing&hellip;</span>
            </button>
        @endif
    </x-card>
</div>
