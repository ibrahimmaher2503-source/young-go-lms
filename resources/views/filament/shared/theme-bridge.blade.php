{{-- Public pages and panels share one stored preference ('theme'); Filament reads 'filament:theme:mode'. Runs before Filament's own theme script. --}}
<script>
    try {
        const stored = window.localStorage.getItem('theme');
        if (stored === 'dark' || stored === 'light') {
            window.localStorage.setItem('filament:theme:mode', stored);
        }
        window.addEventListener('theme-changed', (event) => {
            if (event.detail === 'dark' || event.detail === 'light') {
                window.localStorage.setItem('theme', event.detail);
            } else {
                window.localStorage.removeItem('theme');
            }
        });
    } catch {
        // Storage can be blocked; Filament falls back to its default mode.
    }
</script>
