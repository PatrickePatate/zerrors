@props(['href', 'active' => false, 'sub' => false])

<a href="{{ $href }}"
   {{ $attributes->class([
        'flex items-center gap-2 px-2.5 py-1.5 text-sm mb-0.5 transition',
        'bg-zinc-100 border border-zinc-300/70 rounded-lg text-gray-900 font-medium' => $active && $sub,
        'font-medium' => $active && !$sub,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => !$active,
   ]) }}>
    {{ $slot }}
</a>
