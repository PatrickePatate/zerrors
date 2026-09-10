@props([])

<label class="flex items-center gap-2 text-sm text-gray-700">
    <input type="checkbox" {{ $attributes->class('h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/10') }}>
    {{ $slot }}
</label>
