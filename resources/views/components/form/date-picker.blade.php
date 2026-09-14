@props(['value' => null, 'label' => null, 'error' => null, 'id' => null, 'size' => 'normal'])

<div>
    @if($label)
        <label for="{{ $id }}" class="mb-1 block {{$size == "small" ? 'text-xs font-normal' : 'text-sm font-medium'}} text-gray-700">{{ $label }}</label>
    @endif

    <div x-data="datePicker(@js($value))"
         class="relative"
         @keydown.escape.window="open = false">
        <input type="hidden" x-ref="hidden" value="{{ $value }}" {{ $attributes }}>

        <button type="button" id="{{ $id }}"
                @click="open = !open"
                :class="open ? 'ring-2 ring-offset-2 ring-gray-400' : ''"
                class="relative flex {{$size == "small" ? 'text-xs h-7' : 'text-sm h-10'}} w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-900 focus:outline-none">
            <span x-text="label || 'Select date…'" :class="label ? 'text-gray-900' : 'text-gray-400'"></span>
            <x-lucide-calendar class="h-4 w-4 shrink-0 text-gray-400" />
        </button>

        <div x-show="open"
             @click.away="open = false"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100"
             class="absolute z-50 mt-1 w-64 rounded-md bg-white p-3 shadow-lg ring-1 ring-black/5"
             x-cloak>
            <div class="mb-2 flex items-center justify-between">
                <button type="button" @click="previousMonth()" class="rounded p-1 text-gray-500 hover:bg-gray-100">
                    <x-lucide-chevron-left class="h-4 w-4" />
                </button>
                <span class="text-sm font-medium text-gray-700" x-text="`${monthNames[month]} ${year}`"></span>
                <button type="button" @click="nextMonth()" class="rounded p-1 text-gray-500 hover:bg-gray-100">
                    <x-lucide-chevron-right class="h-4 w-4" />
                </button>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-400">
                <template x-for="day in days" :key="day">
                    <span x-text="day"></span>
                </template>
            </div>

            <div class="mt-1 grid grid-cols-7 gap-1 text-center text-sm">
                <template x-for="blank in blankDaysInMonth" :key="'blank-' + blank">
                    <span></span>
                </template>
                <template x-for="day in daysInMonth" :key="day">
                    <button type="button"
                            @click="selectDay(day)"
                            :class="{
                                'bg-gray-900 text-white': isSelected(day),
                                'text-gray-700 hover:bg-gray-100': !isSelected(day),
                                'ring-1 ring-gray-300': isToday(day) && !isSelected(day),
                            }"
                            class="flex h-8 w-8 items-center justify-center rounded-full"
                            x-text="day">
                    </button>
                </template>
            </div>
        </div>
    </div>

    @if($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
