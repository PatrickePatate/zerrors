@props(['value'])

<div {{ $attributes->class('flex items-start gap-2 rounded bg-gray-100 px-1.5 py-1 text-xs text-gray-600 transition-colors') }}
     x-data="{ copied: false }"
     :class="copied && 'bg-gray-900/5 ring-1 ring-gray-900/10'"
>
    <code class="min-w-0 flex-1 break-all">{{ $value }}</code>
    <button type="button"
            @click="$clipboard(@js($value)); copied = true; clearTimeout($el._copiedTimeout); $el._copiedTimeout = setTimeout(() => copied = false, 1500)"
            class="relative shrink-0 text-gray-500 transition hover:text-gray-700 active:scale-90"
    >
        <span class="relative block h-3.5 w-3.5">
            <x-lucide-copy-check
                x-cloak x-show="copied"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="scale-50 opacity-0"
                x-transition:enter-end="scale-100 opacity-100"
                class="absolute inset-0 h-3.5 w-3.5 text-emerald-600"
            />
            <x-lucide-copy
                x-show="!copied"
                x-transition:enter="ease-out duration-150"
                x-transition:enter-start="scale-50 opacity-0"
                x-transition:enter-end="scale-100 opacity-100"
                class="absolute inset-0 h-3.5 w-3.5"
            />
        </span>

        <span
            x-cloak x-show="copied"
            x-transition:enter="ease-out duration-150"
            x-transition:enter-start="-translate-y-0.5 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute bottom-full left-1/2 mb-1 -translate-x-1/2 rounded bg-gray-900 px-1.5 py-0.5 text-[10px] font-medium whitespace-nowrap text-white"
        >
            Copied!
        </span>
    </button>
</div>
