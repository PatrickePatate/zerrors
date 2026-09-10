@php
    $user = $event->payload['user'] ?? null;
    $request = $event->request;
    $contexts = $event->contexts;
    $extra = $event->extra;
@endphp

@if($user || $request || $contexts || $extra)
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @if($user)
            <div>
                <h3 class="mb-2 text-xs font-medium tracking-wide text-gray-600 uppercase">User</h3>
                <dl class="space-y-1 text-sm">
                    @foreach(['id', 'username', 'email', 'ip_address'] as $key)
                        @continue(empty($user[$key]))
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-gray-400">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="text-gray-700">{{ is_scalar($user[$key]) ? $user[$key] : json_encode($user[$key]) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if($request)
            <div>
                <h3 class="mb-2 text-xs font-medium tracking-wide text-gray-600 uppercase">Request</h3>
                <dl class="space-y-1 text-sm">
                    @if(!empty($request['method']) || !empty($request['url']))
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-gray-400">URL</dt>
                            <dd class="break-all text-gray-700">
                                <span class="font-mono text-xs text-indigo-600">{{ $request['method'] ?? 'GET' }}</span>
                                {{ $request['url'] ?? '' }}
                            </dd>
                        </div>
                    @endif
                    @if(!empty($request['query_string']))
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-gray-400">Query</dt>
                            <dd class="break-all font-mono text-xs text-gray-700">{{ $request['query_string'] }}</dd>
                        </div>
                    @endif
                    @foreach(($request['headers'] ?? []) as $key => $value)
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-gray-400">{{ $key }}</dt>
                            <dd class="break-all text-gray-700">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if($contexts)
            <div>
                <h3 class="mb-2 text-xs font-medium tracking-wide text-gray-600 uppercase">Context</h3>
                <dl class="space-y-1 text-sm">
                    @foreach($contexts as $group => $values)
                        @if(is_array($values))
                            @foreach($values as $key => $value)
                                @continue(is_array($value))
                                <div class="flex gap-2">
                                    <dt class="w-28 shrink-0 text-gray-400">{{ $group }}.{{ $key }}</dt>
                                    <dd class="text-gray-700">{{ $value }}</dd>
                                </div>
                            @endforeach
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif

        @if($extra)
            <div>
                <h3 class="mb-2 text-xs font-medium tracking-wide text-gray-600 uppercase">Extra</h3>
                <dl class="space-y-1 text-sm">
                    @foreach($extra as $key => $value)
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-gray-400">{{ $key }}</dt>
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
