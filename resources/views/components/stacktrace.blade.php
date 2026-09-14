<div class="space-y-6">
    @foreach($values as $value)
        <div>
            <p class="mb-3 flex flex-wrap items-center gap-2">
                @if($value->handled !== null)
                    <x-badge :color="$value->handled ? 'green' : 'red'">
                        {{ $value->handled ? 'Handled' : 'Unhandled' }}
                    </x-badge>
                @endif
                <span class="font-semibold text-red-600">{{ $value->type }}</span>
                @if($value->message)
                    <span class="ml-1 text-gray-700">{{ $value->message }}</span>
                @endif
            </p>

            @if($value->frames !== [])
                <div class="space-y-2">
                    @foreach($value->frames as $frame)
                        <div x-data="{ expanded: {{ $loop->index === $value->importantFrameIndex ? 'true' : 'false' }} }"
                             @class([
                                'overflow-hidden rounded-lg border',
                                'border-gray-900/10' => $frame->inApp,
                                'border-gray-100' => !$frame->inApp,
                            ])>
                            <button
                                type="button"
                                @click="expanded = !expanded"
                                @class([
                                    'relative flex w-full items-start bg-gray-50 px-3 py-2 text-left text-xs hover:bg-gray-100',
                                    'opacity-70' => !$frame->inApp,
                                ])
                                @if(!$frame->isExpandable()) disabled @endif
                            >
                                @if(!$frame->inApp)
                                    <x-badge class="absolute top-1.5 right-3">vendor</x-badge>
                                @endif
                                <span class="flex flex-1 flex-wrap items-center gap-2 {{ !$frame->inApp ? 'pr-14' : '' }}">
                                    @if($frame->isExpandable())
                                        <x-lucide-chevron-right class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform" ::class="{ 'rotate-90': expanded }" />
                                    @else
                                        <span class="h-3.5 w-3.5 shrink-0"></span>
                                    @endif
                                    <span class="font-mono text-gray-700">{{ $frame->filename }}</span>
                                    @if($frame->lineNumber)
                                        <span class="font-mono text-indigo-600">:{{ $frame->lineNumber }}</span>
                                    @endif
                                    @if($frame->functionName)
                                        <span class="text-gray-400">in {{ $frame->functionName }}()</span>
                                    @endif
                                </span>
                            </button>

                            <div x-show="expanded" x-collapse x-cloak>
                                @if($frame->hasContext())
                                    <div class="overflow-x-auto bg-white font-mono text-xs leading-relaxed">
                                        @foreach($frame->codeLines as $codeLine)
                                            <div @class(['flex', 'bg-red-50 min-w-full w-max' => $codeLine['highlighted']])>
                                                <span @class([
                                                    'w-10 shrink-0 select-none border-r px-2 py-0.5 text-right',
                                                    'border-red-100 text-red-500' => $codeLine['highlighted'],
                                                    'border-gray-100 text-gray-400' => !$codeLine['highlighted'],
                                                ])>{{ $codeLine['line'] }}</span>
                                                <span @class([
                                                    'whitespace-pre px-3 py-0.5',
                                                    'text-red-700' => $codeLine['highlighted'],
                                                    'text-gray-600' => !$codeLine['highlighted'],
                                                ])><code class="language-{{ $frame->language }}">{{ $codeLine['code'] }}</code></span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if($frame->hasVars())
                                    <div class="border-t border-gray-100 px-3 py-2 text-xs">
                                        <p class="mb-1 text-gray-500">Local variables</p>
                                        <table>
                                            @foreach($frame->vars as $name => $formattedValue)
                                                <tr>
                                                    <td class="py-0.5 pr-3 font-mono text-indigo-600">{{ $name }}</td>
                                                    <td class="py-0.5"><code class="text-gray-600">{{ $formattedValue }}</code></td>
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
