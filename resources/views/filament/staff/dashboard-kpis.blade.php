{{-- T-P9-07: role KPIs (report-backed) and live work queues. Rows are prepared by WorkspaceDashboard: label, lines, empty, secondary, url. --}}
@php
    // N3: a tile for a feature that is off or has no data yet (AI) is hidden, not shown as a dash.
    $kpis = array_values(array_filter($kpis, static fn (array $kpi): bool => ! ($kpi['empty'] && str_starts_with($kpi['key'], 'ai_'))));
    $allClear = $queues !== [] && collect($queues)->every(static fn (array $queue): bool => $queue['empty']);
@endphp
<div data-role-dashboard>
    @if ($kpis !== [])
        <section class="yg-dashboard-tools" aria-labelledby="yg-kpi-title">
            <header><h2 id="yg-kpi-title">{{ __('dashboard.kpis_title') }}</h2><p>{{ __('dashboard.kpis_period', ['days' => $days]) }}</p></header>
            <dl class="yg-kpi-strip">
                @foreach ($kpis as $kpi)
                    <div class="yg-summary-item" data-tone="{{ $kpi['tone'] }}" data-kpi="{{ $kpi['key'] }}">
                        <x-filament::icon :icon="$kpi['icon']" class="yg-summary-icon" aria-hidden="true" />
                        <dt class="yg-summary-label" title="{{ $kpi['label'] }}">{{ $kpi['label'] }}</dt>
                        <dd class="yg-summary-value" data-kpi-value="{{ $kpi['raw'] }}">
                            @foreach ($kpi['lines'] as $line)
                                <bdi dir="ltr">{{ $line }}</bdi>@if (! $loop->last) <span aria-hidden="true">·</span> @endif
                            @endforeach
                        </dd>
                        @if ($kpi['empty'])
                            <dd class="yg-workspace-note" data-kpi-empty>{{ __('dashboard.empty_period') }}</dd>
                        @elseif ($kpi['secondary'] !== null)
                            <dd class="yg-workspace-note">{{ $kpi['secondary_label'] }}: <bdi dir="ltr">{{ $kpi['secondary'] }}</bdi></dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @if ($queues !== [])
        <section class="yg-dashboard-tools" aria-labelledby="yg-queue-title">
            <header><h2 id="yg-queue-title">{{ __('dashboard.queues_title') }}</h2><p>{{ __('dashboard.queues_description') }}</p></header>
            @if ($allClear)
                <div class="yg-dashboard-clear" data-queues-clear>
                    <x-filament::icon icon="heroicon-o-check-circle" aria-hidden="true" />
                    <p><strong data-queue-empty>{{ __('dashboard.queues_all_clear') }}</strong> <span>{{ collect($queues)->pluck('label')->implode(' · ') }}</span></p>
                </div>
            @else
            <div class="yg-dashboard-links">
                @foreach ($queues as $queue)
                    @php($tag = $queue['url'] ? 'a' : 'div')
                    <{{ $tag }} @if ($queue['url']) href="{{ $queue['url'] }}" @endif class="yg-dashboard-link" data-queue="{{ $queue['key'] }}" data-queue-count="{{ $queue['raw'] }}">
                        <x-filament::icon :icon="$queue['icon']" aria-hidden="true" />
                        <span>
                            {{ $queue['label'] }}
                            @if ($queue['secondary'] !== null && $queue['secondary'] > 0)
                                <small class="yg-workspace-note">{{ $queue['secondary_label'] }}: <bdi dir="ltr">{{ $queue['secondary'] }}</bdi></small>
                            @endif
                        </span>
                        <strong>
                            @if ($queue['empty'])
                                <span data-queue-empty>{{ __('dashboard.queue_clear') }}</span>
                            @else
                                @foreach ($queue['lines'] as $line)<bdi dir="ltr">{{ $line }}</bdi>@if (! $loop->last) · @endif @endforeach
                            @endif
                        </strong>
                    </{{ $tag }}>
                @endforeach
            </div>
            @endif
        </section>
    @endif

    @if ($kpis === [] && $queues === [])
        <section class="yg-dashboard-tools">
            <div class="yg-dashboard-empty"><x-filament::icon icon="heroicon-o-chart-bar" aria-hidden="true" /><h3>{{ __('dashboard.empty_title') }}</h3><p>{{ __('dashboard.empty_description') }}</p></div>
        </section>
    @endif
</div>
