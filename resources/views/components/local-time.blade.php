@props([
    'at',
    'format' => 'datetime',
])

@php
    $fallbackFormats = [
        'date' => 'Y-m-d',
        'time' => 'H:i',
        'datetime' => 'Y-m-d H:i',
    ];
@endphp

@if($at)
    <time
        datetime="{{ $at->toIso8601String() }}"
        data-local-time
        data-format="{{ $format }}"
        {{ $attributes }}
    >{{ $at->format($fallbackFormats[$format] ?? $fallbackFormats['datetime']) }} UTC</time>
@endif
