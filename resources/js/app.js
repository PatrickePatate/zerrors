import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';
import Clipboard from '@ryangjchandler/alpine-clipboard';
import Prism from 'prismjs';
import monitorChart from './monitor-chart';

// window.livewireScriptConfig is set inline in the layout <head> (before this
// module loads) with the CSRF token + update endpoint URI that Livewire's
// request layer needs — without it, every component update throws trying to
// read `.uri` off an undefined config object. Setting it also suppresses
// Livewire's own DOMContentLoaded auto-start, so we start it ourselves here.
document.addEventListener('alpine:init', () => {
    Alpine.plugin(collapse);
    Alpine.plugin(Clipboard);

    // Backs the response-time chart on the monitor detail page.
    Alpine.data('monitorChart', monitorChart);

    // Backs the <x-form.select> component: a Pines-style custom dropdown
    // that stays wireable by mirroring its value onto a hidden native
    // input and firing input/change events so wire:model / wire:change
    // (and plain form submits) work exactly like a real <select>.
    Alpine.data('formSelect', (items, selected) => ({
        open: false,
        items,
        selectedValue: selected,
        activeItem: null,
        id: null,
        // The options list is teleported to <body> and positioned with fixed
        // coordinates computed from the button's rect, rather than absolutely
        // positioned inside this component — an absolute panel gets clipped
        // (and forces a scrollbar) whenever the select lives inside an
        // `overflow-hidden`/`overflow-x-auto` ancestor, e.g. a table wrapper.
        listStyle: '',

        init() {
            this.id = this.$id('form-select');
            this.activeItem = this.selectedItem;
        },

        updatePosition() {
            const rect = this.$refs.button.getBoundingClientRect();
            this.listStyle = `top:${rect.bottom}px; left:${rect.left}px; width:${rect.width}px;`;
        },

        get selectedItem() {
            return this.items.find((item) => item.value === this.selectedValue) ?? null;
        },

        isActive(item) {
            return this.activeItem !== null && this.activeItem.value === item.value;
        },

        select(item) {
            if (item.disabled) {
                return;
            }

            this.selectedValue = item.value;
            this.activeItem = item;
            this.open = false;
            this.$refs.hidden.value = item.value;
            this.$refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true }));
            this.$refs.button.focus();
        },

        openAndActivateSelected() {
            this.activeItem = this.selectedItem ?? this.items.find((item) => !item.disabled) ?? null;
            this.updatePosition();
            this.open = true;
        },

        activateNext() {
            const enabled = this.items.filter((item) => !item.disabled);
            const index = enabled.indexOf(this.activeItem);
            if (index < enabled.length - 1) {
                this.activeItem = enabled[index + 1];
                this.scrollToActive();
            }
        },

        activatePrevious() {
            const enabled = this.items.filter((item) => !item.disabled);
            const index = enabled.indexOf(this.activeItem);
            if (index > 0) {
                this.activeItem = enabled[index - 1];
                this.scrollToActive();
            }
        },

        scrollToActive() {
            this.$nextTick(() => {
                const el = this.activeItem && document.getElementById(this.activeItem.value + '-' + this.id);
                if (!el || !this.$refs.list) {
                    return;
                }
                const newScroll = (el.offsetTop + el.offsetHeight) - this.$refs.list.offsetHeight;
                this.$refs.list.scrollTop = newScroll > 0 ? newScroll : 0;
            });
        },
    }));

    // Backs <x-command-palette>: a Pines-style cmd+k command palette. Alpine
    // owns open/close + keyboard roving-highlight state; the results list
    // itself is rendered server-side by the wrapped Livewire component, so
    // the "active" item is tracked by DOM element rather than by index into
    // a JS array.
    Alpine.data('commandPalette', () => ({
        open: false,
        activeIndex: 0,

        init() {
            this.$watch('open', (value) => {
                if (value) {
                    this.activeIndex = 0;
                    // $refs.panel wraps the nested Livewire command-palette component;
                    // Alpine's $refs don't reach into a Livewire child's own subtree, so
                    // the search input and results are found by querying from here instead.
                    this.$nextTick(() => this.$refs.panel?.querySelector('input')?.focus());
                }
            });
        },

        onGlobalKeydown(e) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                this.open = !this.open;
            }
        },

        items() {
            return this.$refs.panel
                ? Array.from(this.$refs.panel.querySelectorAll('[data-command-item]'))
                : [];
        },

        isActive(el) {
            return this.items()[this.activeIndex] === el;
        },

        setActive(el) {
            this.activeIndex = this.items().indexOf(el);
        },

        moveActive(delta) {
            const items = this.items();
            if (!items.length) {
                return;
            }
            this.activeIndex = (this.activeIndex + delta + items.length) % items.length;
            items[this.activeIndex].scrollIntoView({ block: 'nearest' });
        },

        selectActive() {
            this.items()[this.activeIndex]?.click();
        },
    }));
});

Livewire.start();

window.Prism = Prism;
Prism.manual = true;

Promise.resolve()
    .then(() => import('prismjs/components/prism-clike'))
    .then(() => import('prismjs/components/prism-markup-templating'))
    .then(() => import('prismjs/components/prism-php'))
    .then(() => import('prismjs/components/prism-javascript'))
    .then(() => import('prismjs/components/prism-jsx'))
    .then(() => import('prismjs/components/prism-typescript'))
    .then(() => import('prismjs/components/prism-tsx'))
    .then(() => import('prismjs/components/prism-python'))
    .then(() => import('prismjs/components/prism-ruby'))
    .then(() => import('prismjs/components/prism-java'))
    .then(() => import('prismjs/components/prism-go'))
    .then(() => import('prismjs/components/prism-rust'))
    .then(() => import('prismjs/components/prism-c'))
    .then(() => import('prismjs/components/prism-cpp'))
    .then(() => import('prismjs/components/prism-csharp'))
    .then(() => import('prismjs/components/prism-json'))
    .then(() => import('prismjs/components/prism-css'))
    .then(() => import('prismjs/components/prism-bash'))
    .then(() => import('prismjs/components/prism-sql'))
    .then(() => import('prismjs/components/prism-yaml'))
    .then(() => Prism.highlightAll());
