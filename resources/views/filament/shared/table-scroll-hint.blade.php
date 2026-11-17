<p class="yg-table-scroll-hint"
    x-data="{
        overflowing: false,
        measure() {
            const table = $el.closest('.fi-ta')?.querySelector('.fi-ta-content-ctn');
            if (!table) return;

            this.overflowing = table.scrollWidth > table.clientWidth + 1;
            if (this.overflowing) {
                table.setAttribute('tabindex', '0');
                table.setAttribute('role', 'region');
                table.setAttribute('aria-label', $el.textContent.trim());
            } else {
                table.removeAttribute('tabindex');
                table.removeAttribute('role');
                table.removeAttribute('aria-label');
            }
        }
    }"
    x-init="$nextTick(() => measure())" x-on:resize.window="measure()" x-show="overflowing" x-cloak>
    <x-filament::icon icon="heroicon-o-arrows-right-left" aria-hidden="true" />
    <span>{{ __('staff.table_scroll_hint') }}</span>
</p>
