<p class="yg-table-scroll-hint"
    x-data="{
        overflowing: false,
        observer: null,
        unsubscribe: null,
        init() {
            this.observer = new ResizeObserver(() => this.measure());
            this.$nextTick(() => this.observeTable());
            this.unsubscribe = Livewire.hook('morphed', ({ el }) => {
                if (el.contains($el)) this.observeTable();
            });
        },
        destroy() {
            this.observer?.disconnect();
            this.unsubscribe?.();
        },
        observeTable() {
            this.observer.disconnect();
            const region = $el.closest('.fi-ta')?.querySelector('.fi-ta-content-ctn');
            if (region) {
                this.observer.observe(region);
                const table = region.querySelector('.fi-ta-table');
                if (table) this.observer.observe(table);
            }
            this.measure();
        },
        measure() {
            const table = $el.closest('.fi-ta')?.querySelector('.fi-ta-content-ctn');
            this.overflowing = !!table && table.scrollWidth > table.clientWidth + 1;
            if (!table) return;
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
    x-show="overflowing" x-cloak>
    <x-filament::icon icon="heroicon-o-arrows-right-left" aria-hidden="true" />
    <span>{{ __('staff.table_scroll_hint') }}</span>
</p>
