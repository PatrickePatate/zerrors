<div x-data="{ quickOpen: {{ $issue->ai_deep_analysis ? 'false' : 'true' }} }">
    <x-card>
        <div class="flex items-start justify-between gap-3 mb-8">
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

        <div wire:loading wire:target="analyze" class="mt-3">
            <div wire:stream="analysis-stream" class="prose prose-sm max-h-[32rem] max-w-none overflow-y-auto text-sm whitespace-pre-wrap text-gray-700"></div>
        </div>

        <div wire:loading.remove wire:target="analyze">
            @if($issue->ai_analysis)
                <div class="mt-3">
                    <button type="button" @click="quickOpen = ! quickOpen" class="flex w-full items-center justify-between gap-1.5 text-left">
                        <h3 class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                            <x-lucide-file-text class="h-4 w-4 text-indigo-500" />
                            Analysis
                        </h3>
                        <x-lucide-chevron-down class="h-4 w-4 shrink-0 text-gray-400 transition-transform" x-bind:class="{ '-rotate-180': quickOpen }" />
                    </button>
                    <div x-show="quickOpen" x-collapse class="prose prose-sm mt-3 max-h-[42rem] max-w-none overflow-y-auto prose-headings:text-sm prose-headings:font-medium prose-headings:text-gray-700">
                        {!! \Illuminate\Support\Str::markdown($issue->ai_analysis) !!}
                    </div>
                </div>

                <div wire:loading wire:target="deepen" class="mt-4 border-t border-gray-100 pt-4">
                    <h3 class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                        <x-lucide-telescope class="h-4 w-4 text-indigo-500" />
                        Deeper explanation
                    </h3>
                    <div wire:stream="deepen-stream" class="prose prose-sm mt-3 max-h-[32rem] max-w-none overflow-y-auto text-sm whitespace-pre-wrap text-gray-700"></div>
                </div>

                <div wire:loading.remove wire:target="deepen">
                    @if($issue->ai_deep_analysis)
                        <div class="mt-4 border-t border-gray-100 pt-4" x-data="{ open: true }">
                            <button type="button" @click="open = ! open" class="flex w-full items-center justify-between gap-1.5 text-left">
                                <h3 class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                    <x-lucide-telescope class="h-4 w-4 text-indigo-500" />
                                    Deeper explanation
                                </h3>
                                <x-lucide-chevron-down class="h-4 w-4 shrink-0 text-gray-400 transition-transform" x-bind:class="{ '-rotate-180': open }" />
                            </button>
                            @if($issue->ai_deep_analyzed_at)
                                <p class="text-xs text-gray-400">Last generated {{ $issue->ai_deep_analyzed_at->diffForHumans() }}</p>
                            @endif
                            <div x-show="open" x-collapse class="prose prose-sm mt-3 max-h-[42rem] max-w-none overflow-y-auto prose-headings:text-sm prose-headings:font-medium prose-headings:text-gray-700">
                                {!! \Illuminate\Support\Str::markdown($issue->ai_deep_analysis) !!}
                            </div>
                        </div>
                    @endif
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
        </div>

        @if($organization->hasAiConfigured())
            <div class="mt-4 flex items-stretch gap-2">
                <button
                    type="button"
                    wire:click="analyze"
                    wire:loading.attr="disabled"
                    wire:target="analyze,deepen"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
                >
                    <span wire:loading wire:target="analyze">
                        <x-lucide-loader-2 class="h-4 w-4 animate-spin" />
                    </span>
                    <span wire:loading.remove wire:target="analyze" class="inline-flex items-center gap-1.5">
                        <x-lucide-sparkles class="h-4 w-4" />
                        {{ $issue->ai_analysis ? 'Re-analyze' : 'Analyze with AI' }}
                    </span>
                    <span wire:loading wire:target="analyze">Analyzing&hellip;</span>
                </button>

                @if($issue->ai_analysis)
                    <button
                        type="button"
                        wire:click="deepen"
                        @click="quickOpen = false"
                        wire:loading.attr="disabled"
                        wire:target="analyze,deepen"
                        title="Ask the AI to think longer and explain in more detail"
                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50"
                    >
                        <span wire:loading wire:target="deepen">
                            <x-lucide-loader-2 class="h-4 w-4 animate-spin" />
                        </span>
                        <span wire:loading.remove wire:target="deepen" class="inline-flex items-center gap-1.5">
                            <x-lucide-telescope class="h-4 w-4" />
                            {{ $issue->ai_deep_analysis ? 'Re-explain in detail' : 'Explain in more detail' }}
                        </span>
                        <span wire:loading wire:target="deepen">Thinking&hellip;</span>
                    </button>
                @endif
            </div>
        @endif
    </x-card>
</div>
