@props(['variant' => 'primary', 'href' => null, 'tag' => null])

@php
    $variants = [
        'primary' => 'bg-gray-900 text-white hover:bg-gray-700 focus-visible:outline-gray-900',
        'secondary' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus-visible:outline-gray-400',
        'danger' => 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600',
    ];
    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none '.($variants[$variant] ?? $variants['primary']);
    $element = $tag ?? ($href ? 'a' : 'button');
@endphp

@if($element === 'a')
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
