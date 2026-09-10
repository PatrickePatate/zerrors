import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';
import Clipboard from '@ryangjchandler/alpine-clipboard';
import Prism from 'prismjs';

// window.livewireScriptConfig is set inline in the layout <head> (before this
// module loads) with the CSRF token + update endpoint URI that Livewire's
// request layer needs — without it, every component update throws trying to
// read `.uri` off an undefined config object. Setting it also suppresses
// Livewire's own DOMContentLoaded auto-start, so we start it ourselves here.
document.addEventListener('alpine:init', () => {
    Alpine.plugin(collapse);
    Alpine.plugin(Clipboard);

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

        init() {
            this.id = this.$id('form-select');
            this.activeItem = this.selectedItem;
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
