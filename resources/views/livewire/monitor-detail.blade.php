<div wire:poll.30s>
    <x-card class="mb-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <x-badge :color="$monitor->current_status->color()" class="flex w-fit items-center gap-1.5 px-2 py-1">
                    {{ $monitor->current_status->label() }}
                </x-badge>
                <h1 class="py-1 text-2xl font-normal text-gray-900">{{ $monitor->name }}</h1>
                <div class="mt-1 flex items-center gap-1.5 text-sm text-gray-600">
                    {{ $monitor->type->icon('4') }} {{ $monitor->type->label() }}
                    @if($monitor->type !== \App\Enums\MonitorType::Ping)
                        <x-badge color="gray">{{ $monitor->http_method->label() }}</x-badge>
                    @endif
                    &middot; {{ $monitor->url }}
                </div>
                @if($monitor->consecutive_failures > 0)
                    <p class="mt-1 text-xs text-red-500">{{ $monitor->consecutive_failures }} consecutive failure(s).</p>
                @endif
                <p class="mt-1 text-xs text-gray-400">Last checked {{ $monitor->last_checked_at?->diffForHumans() ?? 'never' }}</p>
            </div>

            <x-button tag="a" href="{{ route('organizations.monitors.edit', [$organization, $monitor]) }}" variant="secondary">
                <x-lucide-settings class="h-4 w-4" />
                Edit
            </x-button>
        </div>
    </x-card>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Uptime (24h)" :value="$uptime24h.'%'" :color="$uptime24h >= 99 ? 'green' : ($uptime24h >= 90 ? 'amber' : 'red')" />
        <x-stat-card label="Uptime (7d)" :value="$uptime7d.'%'" :color="$uptime7d >= 99 ? 'green' : ($uptime7d >= 90 ? 'amber' : 'red')" />
        <x-stat-card label="Uptime (30d)" :value="$uptime30d.'%'" :color="$uptime30d >= 99 ? 'green' : ($uptime30d >= 90 ? 'amber' : 'red')" />
    </div>

    @if($monitor->check_certificate)
        <x-card class="mb-6">
            <h2 class="mb-3 text-sm font-medium text-gray-700">SSL certificate</h2>
            @if($certificateExpiresAt)
                @php
                    $certColor = $certificateDaysRemaining < 0
                        ? 'red'
                        : ($certificateDaysRemaining <= $monitor->certificate_expiry_warning_days ? 'amber' : 'green');
                @endphp
                <div class="flex items-center gap-2">
                    <x-badge :color="$certColor">
                        {{ $certificateDaysRemaining < 0 ? 'Expired' : 'Valid' }}
                    </x-badge>
                    <p class="text-sm text-gray-600">
                        @if($certificateDaysRemaining < 0)
                            Expired {{ $certificateExpiresAt->diffForHumans() }} ({{ $certificateExpiresAt->toFormattedDateString() }})
                        @else
                            Expires in {{ $certificateDaysRemaining }} day(s) &middot; {{ $certificateExpiresAt->toFormattedDateString() }}
                        @endif
                    </p>
                </div>
            @else
                <p class="text-sm text-gray-400">No certificate data yet &mdash; it will appear after the next check.</p>
            @endif
        </x-card>
    @endif

    @if($monitor->type === \App\Enums\MonitorType::LaravelHealth)
        <x-card class="mb-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-medium text-gray-700">Health checks</h2>
                @if($healthChecksCheckedAt)
                    <span class="text-xs text-gray-400">as of {{ $healthChecksCheckedAt->diffForHumans() }}</span>
                @endif
            </div>

            @if(empty($healthChecks))
                <p class="text-sm text-gray-400">No health check data yet &mdash; it will appear after the next check.</p>
            @else
                @php
                    $healthColors = ['ok' => 'green', 'warning' => 'amber', 'failed' => 'red', 'crashed' => 'red', 'skipped' => 'gray'];
                @endphp
                <ul class="divide-y divide-gray-100">
                    @foreach($healthChecks as $result)
                        <li class="flex items-start justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex gap-2.5 items-baseline">
                                <p class="text-sm font-medium text-gray-900">{{ $result['label'] ?? $result['name'] ?? 'Check' }}</p>
                                @if(! empty($result['shortSummary']))
                                    <span class="text-xs text-gray-600">{{ $result['shortSummary'] }}</span>
                                @endif
                                @if(! empty($result['notificationMessage']))
                                    <p class="text-xs text-gray-500">{{ $result['notificationMessage'] }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-badge :color="$healthColors[$result['status'] ?? ''] ?? 'gray'">
                                    {{ ucfirst($result['status'] ?? 'unknown') }}
                                </x-badge>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif

    <x-card class="mb-6">
        <h2 class="mb-3 text-sm font-medium text-gray-700">Recent checks</h2>
        <x-monitor.uptime-bar :checks="$recentChecks" />
    </x-card>

    <x-card>
        <h2 class="mb-3 text-sm font-medium text-gray-700">Response time</h2>
        <div
            x-data="monitorChart(@js($chartChecks))"
            x-init="init()"
            wire:ignore
            class="h-64 w-full -mt-4 mb-7"
        ></div>
    </x-card>
</div>
