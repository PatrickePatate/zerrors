@if($issue->ai_analysis)
    <x-card x-data="{ quickOpen: {{ $issue->ai_deep_analysis ? 'false' : 'true' }} }">
        <h2 class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
            <x-lucide-sparkles class="h-4 w-4 text-indigo-500" />
            AI analysis
        </h2>

        <div class="mt-3">
            <button type="button" @click="quickOpen = ! quickOpen" class="flex w-full items-center justify-between gap-1.5 text-left">
                <h3 class="flex items-center gap-1.5 text-sm font-medium text-gray-700">
                    <x-lucide-file-text class="h-4 w-4 text-indigo-500" />
                    Analysis
                </h3>
                <x-lucide-chevron-down class="h-4 w-4 shrink-0 text-gray-400 transition-transform" x-bind:class="{ '-rotate-180': quickOpen }" />
            </button>
            @if($issue->ai_analyzed_at)
                <p class="text-xs text-gray-400">Last analyzed {{ $issue->ai_analyzed_at->diffForHumans() }}</p>
            @endif

            <div x-show="quickOpen" x-collapse class="prose prose-sm mt-3 max-h-[42rem] max-w-none overflow-y-auto prose-headings:text-sm prose-headings:font-medium prose-headings:text-gray-700">
                {!! \Illuminate\Support\Str::markdown($issue->ai_analysis) !!}
            </div>
        </div>

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
    </x-card>
@endif
