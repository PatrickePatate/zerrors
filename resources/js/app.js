import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import collapse from '@alpinejs/collapse';
import Prism from 'prismjs';

// window.livewireScriptConfig is set inline in the layout <head> (before this
// module loads) with the CSRF token + update endpoint URI that Livewire's
// request layer needs — without it, every component update throws trying to
// read `.uri` off an undefined config object. Setting it also suppresses
// Livewire's own DOMContentLoaded auto-start, so we start it ourselves here.
document.addEventListener('alpine:init', () => {
    Alpine.plugin(collapse);
});

Livewire.start();

window.Prism = Prism;
Prism.manual = true;

Promise.resolve()
    .then(() => import('prismjs/components/prism-clike'))
    .then(() => import('prismjs/components/prism-markup-templating'))
    .then(() => import('prismjs/components/prism-php'))
    .then(() => Prism.highlightAll());
