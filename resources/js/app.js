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
