@props(['checks'])

@php
    $blocks = collect($checks);
    $colors = [
        'up' => 'bg-green-500',
        'down' => 'bg-red-500',
        'unknown' => 'bg-gray-200',
    ];
@endphp

<div class="flex items-center gap-0.5">
    @forelse($blocks as $check)
        <x-tooltip :message="$check->status->label() .' - '. $check->response_time_ms.'ms<br>'.$check->checked_at->diffForHumans()" class="flex-1">
            <span class="{{ $colors[$check->status->value] ?? $colors['unknown'] }} block h-6 w-full rounded-sm"></span>
        </x-tooltip>
    @empty
        <p class="text-sm text-gray-400">No checks recorded yet.</p>
    @endforelse
    @if($blocks->isNotEmpty())
        @for($i = 0; $i < max(0, 50 - count($blocks)); $i++)
            <span class="bg-gray-200 h-6 flex-1 rounded-sm"></span>
        @endfor
    @endif
</div>
