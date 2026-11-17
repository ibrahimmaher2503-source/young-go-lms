{{-- Props: content (editor JSON), lesson, viewer. Renders only what App\Domain\Content\RichContent rebuilds from its allowlist (ED-003/ED-031). --}}
@props(['content', 'lesson' => null, 'viewer' => null])
<style>
    .rich-content { line-height: 1.9; }
    .rich-content > * + * { margin-block-start: 1rem; }
    .rich-content h2 { font-size: 1.5rem; font-weight: 800; }
    .rich-content h3 { font-size: 1.25rem; font-weight: 700; }
    .rich-content h4 { font-size: 1.125rem; font-weight: 700; }
    .rich-content ul { list-style: disc; padding-inline-start: 1.5rem; }
    .rich-content ol { list-style: decimal; padding-inline-start: 1.5rem; }
    .rich-content blockquote { border-inline-start: 4px solid var(--yg-color-primary); padding-inline-start: 1rem; color: var(--yg-color-muted); }
    .rich-content a { color: var(--yg-color-primary); text-decoration: underline; }
    .rich-content hr { border-color: var(--yg-color-border); }
    .rich-content .rc-table { overflow-x: auto; }
    .rich-content table { border-collapse: collapse; inline-size: 100%; }
    .rich-content th, .rich-content td { border: 1px solid var(--yg-color-border); padding: .5rem .75rem; text-align: start; }
    .rich-content th { background: var(--yg-color-surface-muted); }
    .rich-content .rc-callout { border-radius: .75rem; padding: .75rem 1rem; background: var(--yg-color-info); color: var(--yg-color-on-info); }
    .rich-content .rc-callout-tip { background: var(--yg-color-success); color: var(--yg-color-on-success); }
    .rich-content .rc-callout-warning { background: var(--yg-color-warning); color: var(--yg-color-on-warning); }
    .rich-content img { max-inline-size: 100%; border-radius: .75rem; }
    .rich-content .rc-embed iframe { inline-size: 100%; aspect-ratio: 16 / 9; border: 0; border-radius: .75rem; }
</style>
<div class="rich-content break-words" data-rich-content>{{ \App\Domain\Content\RichContent::html($content, $lesson, $viewer) }}</div>
