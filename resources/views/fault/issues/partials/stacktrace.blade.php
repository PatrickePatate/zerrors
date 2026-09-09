@php
    $values = collect($exception['values'] ?? [])->reverse();
@endphp

<div class="space-y-6">
    @foreach($values as $value)
        <div>
            <p class="mb-3">
                <span class="font-semibold text-red-600">{{ $value['type'] ?? 'Error' }}</span>
                @if(!empty($value['value']))
                    <span class="ml-1 text-gray-700">{{ $value['value'] }}</span>
                @endif
            </p>

            @php
                $frames = collect($value['stacktrace']['frames'] ?? [])->reverse()->values();
                $importantIndex = $frames->search(fn ($f) => ($f['in_app'] ?? true) === true);
                $importantIndex = $importantIndex === false ? 0 : $importantIndex;
            @endphp

            @if($frames->isNotEmpty())
                <div class="space-y-2">
                    @foreach($frames as $frame)
                        @php
                            $inApp = $frame['in_app'] ?? true;
                            $hasContext = !empty($frame['context_line']) || !empty($frame['pre_context']) || !empty($frame['post_context']);
                            $hasVars = !empty($frame['vars']);
                            $startLine = ($frame['lineno'] ?? 0) - count($frame['pre_context'] ?? []);
                        @endphp
                        <div x-data="{ expanded: {{ $loop->index === $importantIndex ? 'true' : 'false' }} }"
                             @class([
                                'overflow-hidden rounded-lg border',
                                'border-gray-900/10' => $inApp,
                                'border-gray-100' => !$inApp,
                            ])>
                            <button
                                type="button"
                                @click="expanded = !expanded"
                                @class([
                                    'relative flex w-full items-start bg-gray-50 px-3 py-2 text-left text-xs hover:bg-gray-100',
                                    'opacity-70' => !$inApp,
                                ])
                                @if(!$hasContext && !$hasVars) disabled @endif
                            >
                                @if(!$inApp)
                                    <x-badge class="absolute top-1.5 right-3">vendor</x-badge>
                                @endif
                                <span class="flex flex-1 flex-wrap items-center gap-2 {{ !$inApp ? 'pr-14' : '' }}">
                                    @if($hasContext || $hasVars)
                                        <x-lucide-chevron-right class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform" ::class="{ 'rotate-90': expanded }" />
                                    @else
                                        <span class="h-3.5 w-3.5 shrink-0"></span>
                                    @endif
                                    <span class="font-mono text-gray-700">{{ $frame['filename'] ?? $frame['abs_path'] ?? '(unknown file)' }}</span>
                                    @if(!empty($frame['lineno']))
                                        <span class="font-mono text-indigo-600">:{{ $frame['lineno'] }}</span>
                                    @endif
                                    @if(!empty($frame['function']))
                                        <span class="text-gray-400">in {{ $frame['function'] }}()</span>
                                    @endif
                                </span>
                            </button>

                            <div x-show="expanded" x-collapse x-cloak>
                                @if($hasContext)
                                    <div class="overflow-x-auto bg-white font-mono text-xs leading-relaxed">
                                        @php $line = $startLine; @endphp
                                        @foreach($frame['pre_context'] ?? [] as $codeLine)
                                            <div class="flex">
                                                <span class="w-10 shrink-0 select-none border-r border-gray-100 px-2 py-0.5 text-right text-gray-400">{{ $line }}</span>
                                                <span class="whitespace-pre px-3 py-0.5 text-gray-600"><code class="language-php">{{ $codeLine }}</code></span>
                                            </div>
                                            @php $line++; @endphp
                                        @endforeach
                                        @if(!empty($frame['context_line']))
                                            <div class="flex bg-red-50">
                                                <span class="w-10 shrink-0 select-none border-r border-red-100 px-2 py-0.5 text-right text-red-500">{{ $line }}</span>
                                                <span class="whitespace-pre px-3 py-0.5 text-red-700"><code class="language-php">{{ $frame['context_line'] }}</code></span>
                                            </div>
                                            @php $line++; @endphp
                                        @endif
                                        @foreach($frame['post_context'] ?? [] as $codeLine)
                                            <div class="flex">
                                                <span class="w-10 shrink-0 select-none border-r border-gray-100 px-2 py-0.5 text-right text-gray-400">{{ $line }}</span>
                                                <span class="whitespace-pre px-3 py-0.5 text-gray-600"><code class="language-php">{{ $codeLine }}</code></span>
                                            </div>
                                            @php $line++; @endphp
                                        @endforeach
                                    </div>
                                @endif

                                @if($hasVars)
                                    <div class="border-t border-gray-100 px-3 py-2 text-xs">
                                        <p class="mb-1 text-gray-500">Local variables</p>
                                        <table>
                                            @foreach($frame['vars'] as $name => $val)
                                                <tr>
                                                    <td class="py-0.5 pr-3 font-mono text-indigo-600">{{ $name }}</td>
                                                    <td class="py-0.5"><code class="text-gray-600">{{ is_scalar($val) ? $val : json_encode($val) }}</code></td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
