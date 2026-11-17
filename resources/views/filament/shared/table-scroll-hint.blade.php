<p class="yg-table-scroll-hint" x-data="{ overflowing: false, measure() { const table = $el.closest('.fi-ta')?.querySelector('.fi-ta-content-ctn'); this.overflowing = !!table && table.scrollWidth > table.clientWidth + 1; } }" x-init="$nextTick(() => measure())" x-on:resize.window.debounce="measure()" x-show="overflowing" x-cloak>
    <x-filament::icon icon="heroicon-o-arrows-right-left" aria-hidden="true" />
    <span>{{ __('staff.table_scroll_hint') }}</span>
</p>
