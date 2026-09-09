@props(['padding' => true, 'variant' => 'default'])

<div {{ $attributes->class([
    'rounded-xl border bg-white',
    'border-gray-200' => $variant === 'default',
    'border-red-200' => $variant === 'danger',
    'p-5' => $padding,
]) }}>
    {{ $slot }}
</div>
