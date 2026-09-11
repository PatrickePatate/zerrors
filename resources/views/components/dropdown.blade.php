@props(['align' => 'left', 'up' => false])

{{--
    The menu is teleported to <body> and positioned with fixed coordinates
    computed from the trigger's bounding rect, rather than absolutely
    positioned inside this div. A plain `absolute` panel gets clipped (and
    forces a scrollbar) whenever the trigger lives inside an
    `overflow-hidden`/`overflow-x-auto` ancestor, e.g. a table wrapper.
--}}
<div
    x-data="{
        dropdownOpen: false,
        menuStyle: '',
        positionBelowTrigger() {
            const rect = $refs.dropdownTrigger.getBoundingClientRect();
            const top = {{ $up ? 'rect.top' : 'rect.bottom' }};
            const left = {{ $align === 'right' ? 'rect.right' : 'rect.left' }};
            this.menuStyle = `top:${top}px; left:${left}px; transform: translate({{ $align === 'right' ? '-100%' : '0' }}, {{ $up ? '-100%' : '0' }});`;
        },
        toggle() {
            if (this.dropdownOpen) {
                this.dropdownOpen = false;
                return;
            }
            this.positionBelowTrigger();
            this.dropdownOpen = true;
        },
        openAt(x, y) {
            this.menuStyle = `top:${y}px; left:${x}px;`;
            this.dropdownOpen = true;
        },
    }"
    class="relative"
    @keydown.escape.window="dropdownOpen = false"
    @scroll.window.capture="dropdownOpen = false"
    @resize.window="dropdownOpen = false"
>
    <div x-ref="dropdownTrigger" @click="toggle()">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
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
            :style="menuStyle"
            {{ $attributes->class([
                'fixed z-50 w-max min-w-max rounded-md border border-neutral-200/70 bg-white p-1 shadow-md text-neutral-700',
            ]) }}
        >
            {{ $slot }}
        </div>
    </template>
</div>
