@props(['label' => null, 'name', 'type' => 'text', 'error' => null])

<div>
    @if($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif
    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'block w-full rounded-lg border px-3 py-2 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-400',
            'border-red-300' => $error,
            'border-gray-300' => !$error,
        ]) }}
    >
    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
