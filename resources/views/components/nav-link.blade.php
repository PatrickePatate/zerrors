@props(['href', 'active' => false])

<a href="{{ $href }}"
   {{ $attributes->class([
        'flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm mb-0.5 transition',
        'bg-gray-900 text-white font-medium' => $active,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900' => !$active,
   ]) }}>
    {{ $slot }}
</a>
