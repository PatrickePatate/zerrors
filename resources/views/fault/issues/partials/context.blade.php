@php
    $user = $event->payload['user'] ?? null;
    $request = $event->request;
    $contexts = $event->contexts;
    $extra = $event->extra;

    $runtime = $contexts['runtime'] ?? null;
    $os = $contexts['os'] ?? null;
    $otherContexts = collect($contexts ?? [])->except(['runtime', 'os'])->all();

    // Sections with a value long enough to look cramped in a half-width
    // column span the full row instead, pushing the sibling column below it.
    $isLong = fn ($value) => is_scalar($value) && mb_strlen((string) $value) > 60;

    $userIsLong = collect($user ?? [])->contains($isLong);

    $requestIsLong = $isLong($request['url'] ?? null)
        || $isLong($request['query_string'] ?? null)
        || collect($request['headers'] ?? [])->contains($isLong);

    $contextIsLong = collect($otherContexts)
        ->flatMap(fn ($values) => is_array($values) ? $values : [])
        ->contains($isLong);

    $extraIsLong = collect($extra ?? [])->contains($isLong);
@endphp

@if($runtime || $os || $event->server_name)
    <div class="mb-4 flex flex-wrap items-center gap-2">
        @if($runtime && (!empty($runtime['name']) || !empty($runtime['version'])))
            <x-tooltip message="Runtime version">
                <div class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600">
                    <x-lucide-cpu class="h-3.5 w-3.5 text-gray-400" />
                    <span class="font-medium text-gray-700">{{ $runtime['name'] ?? 'Runtime' }}</span>
                    @if(!empty($runtime['version'])) <span>{{ $runtime['version'] }}</span> @endif
                </div>
            </x-tooltip>
        @endif

        @if($os && (!empty($os['name']) || !empty($os['version'])))
            <x-tooltip message="OS version">
                <div class="inline-flex max-w-xs items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600">
                    <x-lucide-server class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    <span class="shrink-0 font-medium text-gray-700">{{ $os['name'] ?? 'OS' }}</span>
                    @if(!empty($os['version'])) <span class="shrink-0">{{ $os['version'] }}</span> @endif
                    @if(!empty($os['kernel_version']))
                        <span class="min-w-0 truncate text-gray-400" title="{{ $os['kernel_version'] }}">&middot; {{ $os['kernel_version'] }}</span>
                    @endif
                </div>
            </x-tooltip>
        @endif

        @if($event->server_name)
            <x-tooltip message="Server that produced this event">
                <div class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600">
                    <x-lucide-hard-drive class="h-3.5 w-3.5 text-gray-400" />
                    <span class="font-medium text-gray-700">{{ $event->server_name }}</span>
                </div>
            </x-tooltip>
        @endif
    </div>
@endif

@if($user || $request || $otherContexts || $extra)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:grid-flow-row-dense">
        @if($user)
            <div @class(['sm:col-span-2' => $userIsLong])>
                <h3 class="mb-2 text-xs font-medium tracking-wide text-gray-600 uppercase">User</h3>
                <dl class="space-y-2 text-sm">
                    @foreach(['id', 'username', 'email', 'ip_address'] as $key)
                        @continue(empty($user[$key]))
                        <div>
                            <dt class="text-xs text-gray-400">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="break-all text-gray-700">{{ is_scalar($user[$key]) ? $user[$key] : json_encode($user[$key]) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if($request)
            <div @class(['sm:col-span-2' => $requestIsLong])>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-xs font-medium tracking-wide text-gray-600 uppercase">Request</h3>

                    @if($event->hasReproducibleRequest())
                        <button type="button"
                                x-data="{ copied: false }"
                                @click="$clipboard(@js(\App\Support\Fault\CurlCommandFormatter::format($event))); copied = true; clearTimeout($el._copiedTimeout); $el._copiedTimeout = setTimeout(() => copied = false, 1500)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-50 hover:text-gray-900">
                            <x-lucide-copy-check x-cloak x-show="copied" class="h-3.5 w-3.5 text-emerald-600" />
                            <x-lucide-terminal x-show="!copied" class="h-3.5 w-3.5" />
                            <span x-text="copied ? 'Copied!' : 'Copy as curl'"></span>
                        </button>
                    @endif
                </div>

                @if(!empty($request['method']) || !empty($request['url']))
                    <p class="mb-2 break-all text-sm text-gray-700">
                        <span class="font-mono text-xs font-semibold text-indigo-600">{{ $request['method'] ?? 'GET' }}</span>
                        {{ $request['url'] ?? '' }}
                    </p>
                @endif

                @if(!empty($request['query_string']))
                    <p class="mb-2 break-all font-mono text-xs text-gray-500">?{{ $request['query_string'] }}</p>
                @endif

                @if(!empty($request['headers']))
                    <div class="overflow-hidden rounded-lg border border-gray-200">
                        <table class="w-full text-xs">
                            <tbody class="divide-y divide-gray-100">
                            @foreach($request['headers'] as $key => $value)
                                <tr>
                                    <td class="w-1/3 bg-gray-50 px-2.5 py-1.5 align-top font-medium whitespace-nowrap text-gray-500">{{ $key }}</td>
                                    <td class="max-w-0 px-2.5 py-1.5 break-all text-gray-700">{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        @if($otherContexts)
            <div @class(['sm:col-span-2' => $contextIsLong])>
                <h3 class="mb-2 text-xs font-medium tracking-wide text-gray-600 uppercase">Context</h3>
                <dl class="space-y-2 text-sm">
                    @foreach($otherContexts as $group => $values)
                        @if(is_array($values))
                            @foreach($values as $key => $value)
                                @continue(is_array($value))
                                <div>
                                    <dt class="text-xs text-gray-400">{{ $group }}.{{ $key }}</dt>
                                    <dd class="break-all text-gray-700">{{ $value }}</dd>
                                </div>
                            @endforeach
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif

        @if($extra)
            <div @class(['sm:col-span-2' => $extraIsLong])>
                <h3 class="mb-2 text-xs font-medium tracking-wide text-gray-600 uppercase">Extra</h3>
                <dl class="space-y-2 text-sm">
                    @foreach($extra as $key => $value)
                        <div>
                            <dt class="text-xs text-gray-400">{{ $key }}</dt>
                            <dd class="break-all text-gray-700">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
@else
    <p class="text-sm text-gray-400">No user or context data captured for this event.</p>
@endif
