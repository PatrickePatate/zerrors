@props(['label' => null, 'name', 'error' => null, 'hint' => null])

<div>
    @if($label)
        <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'flex w-full rounded-md border px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-900/10',
            'border-red-300' => $error,
            'border-gray-300' => !$error,
        ]) }}
    >{{ $slot }}</textarea>
    @if($hint)
        <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
    @endif
    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
