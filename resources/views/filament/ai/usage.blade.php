<x-filament-panels::page>
    @include('filament.ai.styles')

    @php
        $money = static fn (int $minor): string => number_format($minor / 100, 2).($currency ? ' '.$currency : '');
        $totals = $report['totals'];
        $maxDay = max(1, collect($report['by_day'])->max('requests') ?? 1);
    @endphp

    <div class="yg-rv" data-assistant-usage>
        <section class="yg-rv-card" aria-label="{{ __('ai_admin.usage.range') }}">
            <p class="yg-rv-meta">{{ __('ai_admin.usage.privacy_note') }}</p>
            <div class="yg-ai-filters">
                <div class="yg-ai-field">
                    <label for="usage-from">{{ __('ai_admin.usage.from') }}</label>
                    <x-filament::input.wrapper><x-filament::input type="date" id="usage-from" wire:model.live="from" max="{{ $range[1] }}" /></x-filament::input.wrapper>
                </div>
                <div class="yg-ai-field">
                    <label for="usage-to">{{ __('ai_admin.usage.to') }}</label>
                    <x-filament::input.wrapper><x-filament::input type="date" id="usage-to" wire:model.live="to" max="{{ now()->toDateString() }}" /></x-filament::input.wrapper>
                </div>
                <span class="yg-rv-meta">{{ __('ai_admin.usage.showing', ['from' => $range[0], 'to' => $range[1]]) }}</span>
            </div>
        </section>

        <dl class="yg-ai-tiles" data-usage-totals>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.requests') }}</dt><dd class="yg-ai-num" data-total="requests">{{ $totals['requests'] }}</dd></div>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.answered') }}</dt><dd class="yg-ai-num" data-total="answered">{{ $totals['answered'] }}</dd></div>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.not_in_source') }}</dt><dd class="yg-ai-num" data-total="not_in_source">{{ $totals['not_in_source'] }}</dd></div>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.cache_hits') }}</dt><dd class="yg-ai-num" data-total="cache_hits">{{ $totals['cache_hits'] }}</dd></div>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.guard_blocks') }}</dt><dd class="yg-ai-num" data-total="guard_blocks">{{ $totals['guard_blocks'] }}</dd></div>
            <div class="yg-ai-tile" @if($totals['errors'] > 0) data-tone="danger" @endif><dt>{{ __('ai_admin.usage.errors') }}</dt><dd class="yg-ai-num" data-total="errors">{{ $totals['errors'] }} <span class="yg-rv-meta">({{ $totals['error_rate'] }}%)</span></dd></div>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.cost') }}</dt><dd class="yg-ai-num" data-total="cost">{{ $money($totals['cost_minor']) }}</dd></div>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.latency') }}</dt><dd class="yg-ai-num" data-total="latency">{{ $totals['avg_latency_ms'] }} / {{ $totals['p95_latency_ms'] }} <span class="yg-rv-meta">{{ __('ai_admin.usage.ms') }}</span></dd></div>
            <div class="yg-ai-tile"><dt>{{ __('ai_admin.usage.tokens') }}</dt><dd class="yg-ai-num" data-total="tokens">{{ $totals['input_tokens'] + $totals['output_tokens'] }}</dd></div>
        </dl>

        @if($totals['requests'] === 0)
            <section class="yg-rv-card" data-usage-empty><p>{{ __('ai_admin.usage.empty') }}</p></section>
        @else
            <div class="yg-rv-grid">
                <section class="yg-rv-card" aria-label="{{ __('ai_admin.usage.by_day') }}" data-section="by-day">
                    <h3 class="yg-rv-title">{{ __('ai_admin.usage.by_day') }}</h3>
                    <div class="yg-ai-scroll" tabindex="0">
                        <table class="yg-ai-table">
                            <thead><tr>
                                <th scope="col">{{ __('ai_admin.usage.day') }}</th>
                                <th scope="col">{{ __('ai_admin.usage.requests') }}</th>
                                <th scope="col">{{ __('ai_admin.usage.errors') }}</th>
                                <th scope="col">{{ __('ai_admin.usage.cost') }}</th>
                            </tr></thead>
                            <tbody>
                            @foreach($report['by_day'] as $day)
                                <tr>
                                    <td class="yg-ai-num">{{ \Illuminate\Support\Carbon::parse($day['day'])->isoFormat('ll') }}</td>
                                    <td class="yg-ai-num">{{ $day['requests'] }}<span class="yg-ai-bar-track" aria-hidden="true"><span class="yg-ai-bar" style="inline-size: {{ round($day['requests'] * 100 / $maxDay) }}%"></span></span></td>
                                    <td class="yg-ai-num">{{ $day['errors'] }}</td>
                                    <td class="yg-ai-num">{{ $money($day['cost_minor']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="yg-rv-card" aria-label="{{ __('ai_admin.usage.by_result') }}" data-section="by-result">
                    <h3 class="yg-rv-title">{{ __('ai_admin.usage.by_result') }}</h3>
                    <ul class="yg-rv-list">
                        @foreach($report['by_result'] as $row)
                            <li class="yg-rv-item" data-result="{{ $row['result'] }}" data-kind="{{ $row['kind'] }}">
                                <span class="yg-ai-row"><span>{{ __('ai_admin.usage.results.'.$row['result']) }}</span><strong class="yg-ai-num">{{ $row['requests'] }}</strong></span>
                                <span class="yg-rv-meta">{{ __('ai_admin.usage.kinds.'.$row['kind']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            <div class="yg-rv-grid">
                <section class="yg-rv-card" aria-label="{{ __('ai_admin.usage.by_lesson') }}" data-section="by-lesson">
                    <h3 class="yg-rv-title">{{ __('ai_admin.usage.by_lesson') }}</h3>
                    <div class="yg-ai-scroll" tabindex="0">
                        <table class="yg-ai-table">
                            <thead><tr>
                                <th scope="col">{{ __('ai_admin.usage.lesson') }}</th>
                                <th scope="col">{{ __('ai_admin.usage.requests') }}</th>
                                <th scope="col">{{ __('ai_admin.usage.errors') }}</th>
                                <th scope="col">{{ __('ai_admin.usage.cost') }}</th>
                            </tr></thead>
                            <tbody>
                            @foreach($report['by_lesson'] as $lesson)
                                <tr><td>{{ $lesson['lesson'] }}</td><td class="yg-ai-num">{{ $lesson['requests'] }}</td><td class="yg-ai-num">{{ $lesson['errors'] }}</td><td class="yg-ai-num">{{ $money($lesson['cost_minor']) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="yg-rv-card" aria-label="{{ __('ai_admin.usage.by_model') }}" data-section="by-model">
                    <h3 class="yg-rv-title">{{ __('ai_admin.usage.by_model') }}</h3>
                    <ul class="yg-rv-list">
                        @foreach($report['by_model'] as $model)
                            <li class="yg-rv-item">
                                <span class="yg-ai-row"><span>{{ __('ai_admin.providers.'.$model['provider']) }}@if($model['model'] !== '') · <bdi dir="ltr">{{ $model['model'] }}</bdi>@endif</span><strong class="yg-ai-num">{{ $model['requests'] }}</strong></span>
                                <span class="yg-rv-meta">{{ $money($model['cost_minor']) }} · {{ $model['avg_latency_ms'] }} {{ __('ai_admin.usage.ms') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
