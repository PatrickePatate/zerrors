@props(['align' => 'left', 'up' => false])

<div x-data="{ dropdownOpen: false }" class="relative" @keydown.escape.window="dropdownOpen = false">
    <div @click="dropdownOpen = ! dropdownOpen">
        {{ $trigger }}
    </div>

    <div
        x-show="dropdownOpen"
        x-on:click.away="dropdownOpen = false"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 {{ $up ? 'translate-y-2' : '-translate-y-2' }}"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        {{ $attributes->class([
            'absolute z-50 w-full min-w-max rounded-md border border-neutral-200/70 bg-white p-1 shadow-md text-neutral-700',
            'left-0' => $align === 'left',
            'right-0' => $align === 'right',
            'bottom-full mb-1' => $up,
            'top-full mt-1' => !$up,
        ]) }}
    >
        {{ $slot }}
    </div>
</div>
