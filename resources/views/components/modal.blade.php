@props(['openOnError' => false, 'maxWidth' => 'md', 'entangle' => null])

@php
    $maxWidths = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl'];
@endphp

{{--
    Plain modals track `open` as local Alpine state. Livewire-backed ones pass
    `entangle="propertyName"` instead: a DOM morph after a component update can
    swap out this element without Alpine rebinding a plain window-event
    listener on it, so closing a modal as a side effect of a Livewire action
    (e.g. after a successful form submit) needs the two-way-bound property
    instead of a dispatched event.
--}}
<div
    x-data="{ open: @if($entangle) @entangle($entangle) @else @js((bool) $openOnError) @endif }"
    @keydown.escape.window="open = false"
    class="inline-block"
>
    <div @click="open = true">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div x-show="open" style="display: none" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-900/40"
                @click="open = false"
            ></div>

            <div
                x-show="open"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.outside="open = false"
                {{ $attributes->class(['relative max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl', $maxWidths[$maxWidth] ?? $maxWidths['md']]) }}
            >
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
