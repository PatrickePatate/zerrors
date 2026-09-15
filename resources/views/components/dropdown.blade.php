@props(['align' => 'left', 'up' => false, 'matchTriggerWidth' => false])

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
            const width = {{ $matchTriggerWidth ? 'true' : 'false' }} ? `width:${rect.width}px;` : '';
            this.menuStyle = `top:${top}px; left:${left}px; ${width} transform: translate({{ $align === 'right' ? '-100%' : '0' }}, {{ $up ? '-100%' : '0' }});`;
        },
        openAt(x, y) {
            this.menuStyle = `top:${y}px; left:${x}px;`;
            this.dropdownOpen = true;
            this.clampToViewport();
        },
        clampToViewport() {
            this.$nextTick(() => {
                const menu = $refs.dropdownMenu;
                if (! menu) return;
                const rect = menu.getBoundingClientRect();
                const margin = 8;
                let deltaX = 0;
                let deltaY = 0;
                if (rect.right > window.innerWidth - margin) {
                    deltaX = (window.innerWidth - margin) - rect.right;
                }
                if (rect.left + deltaX < margin) {
                    deltaX = margin - rect.left;
                }
                if (rect.bottom > window.innerHeight - margin) {
                    deltaY = (window.innerHeight - margin) - rect.bottom;
                }
                if (rect.top + deltaY < margin) {
                    deltaY = margin - rect.top;
                }
                if (deltaX !== 0 || deltaY !== 0) {
                    menu.style.transform += ` translate(${deltaX}px, ${deltaY}px)`;
                }
            });
        },
        toggle() {
            if (this.dropdownOpen) {
                this.dropdownOpen = false;
                return;
            }
            this.positionBelowTrigger();
            this.dropdownOpen = true;
            this.clampToViewport();
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
            x-ref="dropdownMenu"
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
                'fixed z-50 w-max max-w-[calc(100vw-1rem)] min-w-max rounded-md border border-neutral-200/70 bg-white p-1 shadow-md text-neutral-700',
            ]) }}
        >
            {{ $slot }}
        </div>
    </template>
</div>
