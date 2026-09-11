@props(['align' => 'left'])

<th {{ $attributes->class([
    'px-5 py-3 font-medium',
    'text-left' => $align === 'left',
    'text-right' => $align === 'right',
    'text-center' => $align === 'center',
]) }}>
    {{ $slot }}
</th>
