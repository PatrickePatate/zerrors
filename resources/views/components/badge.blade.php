@props(['color' => 'gray'])

@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700',
        'red' => 'bg-red-100 text-red-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'blue' => 'bg-blue-100 text-blue-700',
        'green' => 'bg-green-100 text-green-700',
        'indigo' => 'bg-indigo-100 text-indigo-700',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', $colors[$color] ?? $colors['gray']]) }}>
    {{ $slot }}
</span>
