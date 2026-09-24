const INTL_OPTIONS = {
    datetime: { dateStyle: 'medium', timeStyle: 'short' },
    date: { dateStyle: 'medium' },
    time: { timeStyle: 'short' },
};

function formatElement(el) {
    const iso = el.getAttribute('datetime');
    const date = iso && new Date(iso);

    if (!date || Number.isNaN(date.getTime())) {
        return;
    }

    const options = INTL_OPTIONS[el.dataset.format] || INTL_OPTIONS.datetime;

    // window.userTimezone is the visitor's stored preference (see auth/security
    // settings); when unset, omitting `timeZone` makes Intl fall back to the
    // browser's own timezone automatically.
    el.textContent = new Intl.DateTimeFormat(undefined, { ...options, timeZone: window.userTimezone || undefined }).format(date);
    el.dataset.localTimeFormatted = 'true';
}

function formatAll(root) {
    root.querySelectorAll('[data-local-time]:not([data-local-time-formatted])').forEach(formatElement);
}

/**
 * Renders every <time data-local-time> element (server-rendered in UTC) in
 * the visitor's timezone. Uses a MutationObserver rather than a Livewire
 * lifecycle hook so it keeps working across Livewire's DOM morphing without
 * depending on its internal event names.
 */
export default function initLocalTime() {
    formatAll(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                if (node.matches('[data-local-time]')) {
                    formatElement(node);
                }

                formatAll(node);
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
}
