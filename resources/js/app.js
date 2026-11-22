import './player.js';
import './live.js';
import './quiz.js';
import './assistant.js';
import './ui-primitives.js';
import './forms.js';
import './public-home.js';

const applyTheme = (dark) => {
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    for (const button of document.querySelectorAll('[data-theme-toggle]')) {
        button.setAttribute('aria-pressed', dark ? 'true' : 'false');
    }
};

try {
    // Pages that fix their own theme (design previews) set data-theme on the server; product pages follow the viewer.
    if (document.documentElement.dataset.theme === undefined) {
        const stored = window.localStorage.getItem('theme');
        const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(dark);
    }
} catch {
    // Storage can be blocked; the light theme remains.
}

document.addEventListener('click', (event) => {
    const toggle = event.target instanceof Element ? event.target.closest('[data-theme-toggle]') : null;
    if (!toggle) {
        return;
    }
    const nextDark = !document.documentElement.classList.contains('dark');
    applyTheme(nextDark);
    try {
        window.localStorage.setItem('theme', nextDark ? 'dark' : 'light');
        // Keep the staff panels (Filament) on the same choice.
        window.localStorage.setItem('filament:theme:mode', nextDark ? 'dark' : 'light');
    } catch {
        // Ignore storage errors when private browsing blocks writes.
    }
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: nextDark ? 'dark' : 'light' }));
});
