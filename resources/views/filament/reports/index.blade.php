<x-filament-panels::page>
    @include('filament.ai.styles')

    @php
        $format = static fn (mixed $cell, string $type): string => match (true) {
            $cell === null || $cell === '' => '',
            $type === 'money' => number_format((float) $cell, 2),
            $type === 'percent' => rtrim(rtrim(number_format((float) $cell, 1), '0'), '.').'%',
            $type === 'int' => number_format((int) $cell),
            $type === 'datetime' => \App\Domain\Reports\ReportLabels::display(\Carbon\CarbonImmutable::parse((string) $cell, 'UTC')->setTimezone($timezone)->format('Y-m-d H:i'), true),
            default => \App\Domain\Reports\ReportLabels::display((string) $cell),
        };
    @endphp

    <div class="yg-rv" data-reports-page data-report="{{ $key->value }}">
        <section class="yg-rv-card" aria-label="{{ __('reports.filters') }}">
            <p class="yg-rv-meta">{{ __('reports.descriptions.'.$key->value) }}</p>
            @if($ownOnly)
                <p class="yg-rv-meta" data-own-only>{{ __('reports.own_only') }}</p>
            @endif
            <div class="yg-ai-filters yg-rv-filters">
                <div class="yg-ai-field" data-field="report">
                    <label for="report-key">{{ __('reports.report') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="report-key" wire:model.live="report" style="background-image: none !important; -webkit-appearance: none !important; -moz-appearance: none !important; appearance: none !important;">
                            @foreach($available as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                @include('filament.reports.date-field', ['prop' => 'from', 'id' => 'report-from', 'label' => __('reports.from')])
                @include('filament.reports.date-field', ['prop' => 'to', 'id' => 'report-to', 'label' => __('reports.to')])
                @if($key->supports('course'))
                    <div class="yg-ai-field" data-field="course">
                        <label for="report-course">{{ __('reports.course') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="report-course" wire:model.live="course" style="background-image: none !important; -webkit-appearance: none !important; -moz-appearance: none !important; appearance: none !important;">
                                <option value="">{{ __('reports.all') }}</option>
                                @foreach($courses as $id => $title)
                                    <option value="{{ $id }}">{{ $title }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
                @if($instructors !== [])
                    <div class="yg-ai-field">
                        <label for="report-instructor">{{ __('reports.instructor') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="report-instructor" wire:model.live="instructor" style="background-image: none !important; -webkit-appearance: none !important; -moz-appearance: none !important; appearance: none !important;">
                                <option value="">{{ __('reports.all') }}</option>
                                @foreach($instructors as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
                @if($showMarket)
                    <div class="yg-ai-field">
                        <label for="report-market">{{ __('reports.market') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="report-market" wire:model.live="market" style="background-image: none !important; -webkit-appearance: none !important; -moz-appearance: none !important; appearance: none !important;">
                                <option value="">{{ __('reports.all') }}</option>
                                <option value="EG">{{ __('reports.markets.EG') }}</option>
                                <option value="SA">{{ __('reports.markets.SA') }}</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
            </div>
            <p class="yg-rv-meta" data-range-caption>{{ __('reports.showing', ['from' => \App\Domain\Reports\ReportLabels::display($range[0]), 'to' => \App\Domain\Reports\ReportLabels::display($range[1])]) }}</p>
        </section>

        @if($totals !== [])
            <dl class="yg-ai-tiles" data-report-totals>
                @foreach($totals as $total)
                    <div class="yg-ai-tile" data-total="{{ $total['key'] }}" @if($total['currency']) data-currency="{{ $total['currency'] }}" @endif>
                        <dt>{{ __('reports.summary.'.$total['key']) }}@if($total['currency']) <span class="yg-rv-meta">({{ $total['currency'] }})</span>@endif</dt>
                        <dd class="yg-ai-num">{{ $total['money'] ? number_format((float) $total['value'], 2) : number_format((float) $total['value']) }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <section class="yg-rv-card" aria-label="{{ $key->label() }}">
            <div class="yg-ai-row">
                <h3 class="yg-rv-title">{{ $key->label() }} <span class="yg-rv-meta" data-row-count>({{ number_format($count) }})</span></h3>
                @if($tooLarge)
                    <p class="yg-ai-error" data-export-too-large>{{ __('reports.too_large', ['rows' => number_format($count), 'cap' => number_format($cap)]) }}</p>
                @else
                    <x-filament::button tag="a" :href="$exportUrl" icon="heroicon-o-arrow-down-tray" data-export-csv>{{ __('reports.export') }}</x-filament::button>
                @endif
            </div>

            @if($rows === [])
                <x-ui.empty-state icon="heroicon-o-chart-bar" :title="__('reports.empty_title')" :description="__('reports.empty')" data-report-empty />
            @else
                <div class="yg-ai-scroll" tabindex="0" role="region" aria-label="{{ $key->label() }}">
                    <table class="yg-ai-table" data-report-table>
                        <thead><tr>
                            @foreach($columns as $column => $type)
                                <th scope="col">{{ str_replace('UTC', $timezone, __('reports.columns.'.$column)) }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach(array_values($columns) as $index => $type)
                                    <td @if(in_array($type, ['money', 'int', 'percent', 'datetime'], true)) class="yg-ai-num" @endif>{{ $format($row[$index] ?? null, $type) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($count > $limit)
                    <p class="yg-rv-meta">{{ __('reports.preview_note', ['shown' => $limit, 'count' => number_format($count)]) }}</p>
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
