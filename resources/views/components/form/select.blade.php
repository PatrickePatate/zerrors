@props([
    'options' => [],
    'images' => [],
    'selected' => null,
    'label' => null,
    'error' => null,
    'placeholder' => 'Select…',
    'id' => null,
])

@php
    $items = collect($options)->map(fn ($optionLabel, $value) => [
        'title' => $optionLabel,
        'value' => (string) $value,
        'image' => $images[$value] ?? null,
        'disabled' => false,
    ])->values()->all();

    // Mirrors native <select> behaviour: with nothing explicitly selected,
    // the first option is selected by default rather than showing a blank state.
    $selected = $selected !== null ? (string) $selected : ($items[0]['value'] ?? null);
@endphp

<div>
    @if($label)
        <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif

    <div x-data="formSelect(@js($items), @js($selected))" class="relative">
        <input type="hidden" x-ref="hidden" value="{{ $selected }}" {{ $attributes }}>

        <button type="button" id="{{ $id }}" x-ref="button"
                @click="open ? (open = false) : openAndActivateSelected()"
                @keydown.escape="open = false"
                @keydown.down.prevent="open ? activateNext() : openAndActivateSelected()"
                @keydown.up.prevent="open ? activatePrevious() : openAndActivateSelected()"
                @keydown.enter.prevent="if (activeItem) select(activeItem)"
                :class="open ? 'ring-2 ring-offset-2 ring-gray-400' : ''"
                class="relative flex h-10 min-h-[38px] w-full items-center justify-between rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 text-left text-sm focus:outline-none">
            <span class="flex min-w-0 items-center gap-2">
                <template x-if="selectedItem?.image">
                    <img :src="selectedItem.image" alt="" class="h-5 w-5 shrink-0 rounded-full object-cover">
                </template>
                <span class="truncate" x-text="selectedItem ? selectedItem.title : @js($placeholder)"></span>
            </span>
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                <x-lucide-chevron-down class="h-4 w-4 text-gray-400" />
            </span>
        </button>

        <ul x-show="open"
            x-ref="list"
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100"
            class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-none"
            x-cloak>
            <template x-for="item in items" :key="item.value">
                <li @click="select(item)"
                    @mousemove="activeItem = item"
                    :id="item.value + '-' + id"
                    :class="isActive(item) ? 'bg-gray-100 text-gray-900' : 'text-gray-700'"
                    class="relative flex cursor-default items-center py-2 pr-3 pl-8 select-none data-disabled:pointer-events-none data-disabled:opacity-50">
                    <x-lucide-check x-show="selectedValue === item.value" class="absolute left-2 h-4 w-4 text-gray-500" />
                    <template x-if="item.image">
                        <img :src="item.image" alt="" class="mr-2 h-5 w-5 shrink-0 rounded-full object-cover">
                    </template>
                    <span class="block truncate font-medium" x-text="item.title"></span>
                </li>
            </template>
        </ul>
    </div>

    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
