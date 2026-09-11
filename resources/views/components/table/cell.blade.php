@props(['align' => 'left'])

<td {{ $attributes->class([
    'px-5 py-3',
    'text-right' => $align === 'right',
    'text-center' => $align === 'center',
]) }}>
    {{ $slot }}
</td>
