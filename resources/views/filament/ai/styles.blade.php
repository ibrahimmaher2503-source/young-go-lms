{{-- Shared rules of the assistant admin pages (T-P7-06): design tokens and logical properties only, so they mirror in RTL. --}}
@include('filament.review.styles')
<style>
    .yg-ai-tiles { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); margin: 0; }
    .yg-ai-tile { display: grid; gap: .25rem; padding: .75rem 1rem; border: 1px solid var(--yg-color-border); border-radius: var(--yg-radius-card); background: var(--yg-color-surface); }
    .yg-ai-tile dt { color: var(--yg-color-muted); font-size: .875rem; }
    .yg-ai-tile dd { margin: 0; font-size: 1.5rem; font-weight: 700; }
    .yg-ai-tile[data-tone="danger"] dd { color: var(--yg-color-danger); }
    .yg-ai-tile[data-tone="success"] dd { color: var(--yg-color-success); }
    .yg-ai-scroll { overflow-x: auto; }
    .yg-ai-table { inline-size: 100%; border-collapse: collapse; font-size: .875rem; }
    .yg-ai-table th, .yg-ai-table td { padding: .5rem .75rem; text-align: start; border-block-end: 1px solid var(--yg-color-border); vertical-align: top; }
    .yg-ai-table thead th { background: var(--yg-color-table-head); font-weight: 700; }
    .yg-ai-num { font-variant-numeric: tabular-nums; }
    .yg-ai-row { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
    .yg-ai-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .yg-ai-field { display: grid; gap: .375rem; }
    .yg-ai-field label { font-weight: 600; }
    .yg-ai-filters { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
    .yg-ai-dot { display: inline-block; inline-size: .75rem; block-size: .75rem; border-radius: 999px; background: var(--yg-color-muted); flex: none; }
    .yg-ai-dot[data-state="ok"] { background: var(--yg-color-success); }
    .yg-ai-dot[data-state="todo"] { background: var(--yg-color-warning); }
    .yg-ai-dot[data-state="open"], .yg-ai-dot[data-state="down"] { background: var(--yg-color-danger); }
    .yg-ai-check { display: flex; gap: .625rem; align-items: flex-start; padding: .5rem .75rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; }
    .yg-ai-check .yg-ai-dot { margin-block-start: .375rem; }
    .yg-ai-bar { display: block; block-size: .5rem; border-radius: 999px; background: var(--yg-color-primary); min-inline-size: 2px; }
    .yg-ai-bar-track { display: block; background: var(--yg-color-page); border-radius: 999px; }
    .yg-ai-switch { min-block-size: 2.75rem; padding: 0 1rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; background: var(--yg-color-surface); font-weight: 600; cursor: pointer; }
    .yg-ai-switch[aria-pressed="true"] { background: var(--yg-color-primary); color: var(--yg-color-on-primary); }
    .yg-ai-error { color: var(--yg-color-danger); font-weight: 600; }
</style>
