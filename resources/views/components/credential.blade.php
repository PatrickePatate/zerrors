@props(['value'])

<div {{ $attributes->class('inline-flex items-center gap-2 rounded bg-gray-100 px-1.5 py-1 text-xs text-gray-600') }}>
    <code>{{ $value }}</code>
    <button type="button" x-data="{ copied: false }"
            @click="$clipboard(@js($value)); copied = true; setTimeout(() => copied = false, 1500)"
            class="text-gray-500 hover:text-gray-700">
        <x-lucide-copy-check x-cloak x-show="copied" class="w-3.5 h-3.5 text-gray-900" />
        <x-lucide-copy x-show="!copied" class="w-3.5 h-3.5" />
    </button>
</div>
