@props(['label', 'value', 'color' => 'gray'])

@php
    $colors = [
        'gray' => 'text-gray-900',
        'red' => 'text-red-600',
        'amber' => 'text-amber-600',
        'blue' => 'text-blue-600',
        'green' => 'text-green-600',
        'indigo' => 'text-indigo-600',
    ];
@endphp

<x-card {{ $attributes }}>
    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
    <p class="mt-1 text-3xl font-semibold {{ $colors[$color] ?? $colors['gray'] }}">{{ $value }}</p>
</x-card>
