{{-- Props: prop (Livewire property holding Y-m-d), id, label. The text shows d/m/Y like the "Showing ..." caption; the calendar button opens the native picker. --}}
<div class="yg-ai-field" data-field="{{ $prop }}">
    <label for="{{ $id }}">{{ $label }}</label>
    <div
        x-data="{
            iso: $wire.$entangle('{{ $prop }}', true),
            text: '',
            init() {
                this.text = this.fmt(this.iso);
                this.$watch('iso', (value) => { this.text = this.fmt(value); });
            },
            fmt(value) {
                const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
                return m ? m[3] + '/' + m[2] + '/' + m[1] : (value || '');
            },
            commit() {
                const m = /^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/.exec(this.text.trim());
                if (m) {
                    this.iso = m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
                }
                this.text = this.fmt(this.iso);
            },
            pick() { this.$refs.native.showPicker?.(); this.$refs.native.focus(); },
        }"
        class="yg-date-field"
    >
        <x-filament::input.wrapper>
            <x-filament::input type="text" id="{{ $id }}" inputmode="numeric" dir="ltr" autocomplete="off" placeholder="{{ __('reports.date_hint') }}" x-model="text" x-on:change="commit()" x-on:keydown.enter.prevent="commit()" />
            <x-slot name="suffix">
                <button type="button" class="yg-date-pick" x-on:click="pick()" aria-label="{{ __('reports.pick_date') }}">
                    <x-filament::icon icon="heroicon-o-calendar-days" aria-hidden="true" />
                </button>
            </x-slot>
        </x-filament::input.wrapper>
        <input type="date" class="yg-date-native" tabindex="-1" aria-hidden="true" x-ref="native" x-model="iso">
    </div>
</div>
