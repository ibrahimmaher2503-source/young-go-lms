import './player.js';
import './live.js';
import './ui-primitives.js';
import './forms.js';

try {
    // Pages that fix their own theme (design previews) set data-theme on the server; product pages follow the viewer.
    if (document.documentElement.dataset.theme === undefined) {
        const stored = window.localStorage.getItem('theme');
        const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    }
} catch {
    // Storage can be blocked; the light theme remains.
}
