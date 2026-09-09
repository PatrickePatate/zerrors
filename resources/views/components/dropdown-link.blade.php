@props(['href' => null, 'tag' => null])

@php($element = $tag ?? ($href ? 'a' : 'button'))

@if($element === 'a')
    <a href="{{ $href }}" {{ $attributes->class('flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100') }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->class('flex w-full items-center gap-2 rounded px-2.5 py-1.5 text-left text-sm text-neutral-700 hover:bg-neutral-100') }}>
        {{ $slot }}
    </button>
@endif
