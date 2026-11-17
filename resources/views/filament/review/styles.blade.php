{{-- Shared rules of the review pages (T-P6-04). The Filament theme does not scan this folder, so they live here and use the shared tokens and logical properties only. --}}
<style>
    .yg-rv { display: grid; gap: 1rem; }
    .yg-rv-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 64rem) { .yg-rv-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
    .yg-rv-card { background: var(--yg-color-surface); border: 1px solid var(--yg-color-border); border-radius: var(--yg-radius-card); box-shadow: var(--yg-shadow-card); padding: 1rem; display: grid; gap: .75rem; align-content: start; }
    .yg-rv-title { font-weight: 700; margin: 0; }
    .yg-rv-meta { color: var(--yg-color-muted); font-size: .875rem; }
    .yg-rv-text { white-space: pre-line; overflow-wrap: anywhere; padding: .75rem; border-radius: .5rem; background: var(--yg-color-page); }
    .yg-rv-badge { display: inline-flex; align-items: center; padding: .125rem .625rem; border-radius: 999px; font-size: .8125rem; font-weight: 600; background: var(--yg-color-page); }
    .yg-rv-list { display: grid; gap: .5rem; margin: 0; padding: 0; list-style: none; }
    .yg-rv-item { display: grid; gap: .25rem; padding: .5rem .75rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; }
    .yg-rv-link { color: var(--yg-color-primary); font-weight: 600; text-decoration: underline; overflow-wrap: anywhere; }
    .yg-rv-btn { min-block-size: 2.75rem; padding: 0 1rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; background: var(--yg-color-primary); color: var(--yg-color-on-primary); font-weight: 600; cursor: pointer; }
    .yg-rv-terms { display: grid; gap: .5rem 1rem; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); margin: 0; }
    .yg-rv-terms dt { color: var(--yg-color-muted); font-size: .875rem; }
    .yg-rv-terms dd { margin: 0; font-weight: 700; }
</style>
