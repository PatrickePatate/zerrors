@props(['muted' => false])

<tr {{ $attributes->class([
    'hover:bg-gray-50',
    'hover:bg-gray-100' => $muted,
    'opacity-60' => $muted,
    'bg-gray-50' => $muted,
]) }}>
    {{ $slot }}
</tr>
