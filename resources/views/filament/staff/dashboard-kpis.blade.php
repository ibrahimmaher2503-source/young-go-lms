{{-- T-P9-07 + D-026: Asymmetric analytical overview matching 01-admin-overview-v2.png while keeping report-backed KPIs & live queues. --}}
@php
    // N3: a tile for a feature that is off or has no data yet (AI) is hidden, not shown as a dash.
    $kpis = array_values(array_filter($kpis, static fn (array $kpi): bool => ! ($kpi['empty'] && str_starts_with($kpi['key'], 'ai_'))));
    $allClear = $queues !== [] && collect($queues)->every(static fn (array $queue): bool => $queue['empty']);
    $isAdminOverview = (bool) ($isAdminOverview ?? false);
    $chart = $chart ?? null;
    $chartDays = (int) ($chartDays ?? 30);
    $recentActivity = $recentActivity ?? null;
    $auditUrl = $auditUrl ?? null;

    $pendingQueuesCount = 0;
    foreach ($queues as $queue) {
        if (! $queue['empty'] && is_numeric($queue['raw'] ?? null)) {
            $pendingQueuesCount += (int) $queue['raw'];
        }
    }

    $hasPrimaryCard = ($isAdminOverview && $chart !== null) || $kpis !== [];
    $hasAsideCard = $queues !== [];
@endphp

<div data-role-dashboard class="yg-role-dashboard">
    @if ($hasPrimaryCard || $hasAsideCard)
        <div class="yg-overview-grid @if ($hasPrimaryCard && $hasAsideCard) has-aside @endif">
            @if ($hasPrimaryCard)
                <section class="yg-overview-card yg-overview-primary" aria-labelledby="{{ $isAdminOverview && $chart !== null ? 'yg-enrollments-chart-title' : 'yg-kpi-title' }}">
                    @if ($isAdminOverview && $chart !== null)
                        @php
                            $points = $chart['points'];
                            $pointCount = count($points);
                            $rawMax = (int) $chart['max'];
                            $yMax = $rawMax <= 4 ? 4 : (int) (ceil($rawMax / 4) * 4);
                            $isRtl = app()->isLocale('ar');
                            $svgWidth = 680;
                            $svgHeight = 228;
                            $padTop = 18;
                            $padBottom = 34;
                            $xLeft = $isRtl ? 18 : 44;
                            $xRight = $isRtl ? 632 : 662;
                            $plotWidth = $xRight - $xLeft;
                            $yBase = $svgHeight - $padBottom;
                            $plotHeight = $yBase - $padTop;
                            $coords = [];

                            foreach ($points as $idx => $point) {
                                $ratio = $pointCount > 1 ? $idx / ($pointCount - 1) : 0.5;
                                $x = $isRtl
                                    ? round($xRight - ($ratio * $plotWidth), 1)
                                    : round($xLeft + ($ratio * $plotWidth), 1);
                                $y = round($yBase - (($point['count'] / max(1, $yMax)) * $plotHeight), 1);
                                $coords[] = ['x' => $x, 'y' => $y, ...$point];
                            }

                            $linePath = '';
                            $areaPath = '';
                            if ($coords !== []) {
                                $first = $coords[0];
                                $last = $coords[count($coords) - 1];
                                $linePath = 'M '.$first['x'].' '.$first['y'];
                                for ($i = 1; $i < count($coords); $i++) {
                                    $prev = $coords[$i - 1];
                                    $curr = $coords[$i];
                                    $xMid = round(($prev['x'] + $curr['x']) / 2, 1);
                                    $linePath .= ' C '.$xMid.' '.$prev['y'].', '.$xMid.' '.$curr['y'].', '.$curr['x'].' '.$curr['y'];
                                }
                                $areaPath = $linePath.' L '.$last['x'].' '.$yBase.' L '.$first['x'].' '.$yBase.' Z';
                            }

                            $yTicks = [0, (int) ($yMax / 4), (int) ($yMax / 2), (int) ($yMax * 3 / 4), $yMax];
                            $xLabelStep = max(1, (int) ceil($pointCount / 10));
                            $shownLabelIdx = [];
                            foreach (array_keys($coords) as $i) {
                                if ($i === $pointCount - 1 || ($i % $xLabelStep === 0 && ($pointCount - 1 - $i) >= $xLabelStep / 2)) {
                                    $shownLabelIdx[] = $i;
                                }
                            }
                            $shownTotal = count($shownLabelIdx);
                            foreach ($coords as $idx => &$coordRef) {
                                $shownPos = array_search($idx, $shownLabelIdx, true);
                                $coordRef['showLabel'] = $shownPos !== false;
                                $coordRef['isMinor'] = $shownPos !== false && $idx !== $pointCount - 1 && (($shownPos % 2) === 1 || ($shownTotal >= 3 && $shownPos === $shownTotal - 2));
                                $coordRef['anchor'] = 'middle';
                                if ($shownPos === 0) {
                                    $coordRef['anchor'] = $isRtl ? 'end' : 'start';
                                } elseif ($idx === $pointCount - 1) {
                                    $coordRef['anchor'] = $isRtl ? 'start' : 'end';
                                }
                            }
                            unset($coordRef);
                        @endphp

                        <header class="yg-overview-card-header">
                            <div class="yg-overview-title-group">
                                <span class="yg-overview-badge-icon" aria-hidden="true">
                                    <x-filament::icon icon="heroicon-o-user-plus" />
                                </span>
                                <div>
                                    <h2 id="yg-enrollments-chart-title">{{ __('dashboard.enrollments_chart_title') }}</h2>
                                    <p>{{ __('dashboard.enrollments_chart_subtitle', ['count' => number_format((int) $chart['total'])]) }}</p>
                                </div>
                            </div>

                            <div class="yg-period-pills" role="group" aria-label="{{ __('dashboard.enrollments_chart_title') }}">
                                @foreach ([7, 30, 90] as $daysOption)
                                    <button
                                        type="button"
                                        wire:click="setChartDays({{ $daysOption }})"
                                        @class(['yg-period-pill', 'is-active' => $chartDays === $daysOption])
                                        aria-pressed="{{ $chartDays === $daysOption ? 'true' : 'false' }}"
                                    >
                                        {{ __('dashboard.period_'.$daysOption) }}
                                    </button>
                                @endforeach
                            </div>
                        </header>

                        <div
                            class="yg-chart-stage"
                            dir="ltr"
                            x-data="{
                                active: null,
                                points: @js(array_map(static fn (array $c): array => ['range' => $c['range'], 'count' => $c['count'], 'xPct' => round(($c['x'] / 680) * 100, 2), 'yPct' => round(($c['y'] / 228) * 100, 2)], $coords))
                            }"
                            @mouseleave="active = null"
                        >
                            <svg
                                viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
                                class="yg-chart-svg"
                                dir="ltr"
                                aria-hidden="true"
                            >
                                <defs>
                                    <linearGradient id="yg-enroll-fill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="var(--yg-color-primary)" stop-opacity="0.24" />
                                        <stop offset="100%" stop-color="var(--yg-color-primary)" stop-opacity="0.01" />
                                    </linearGradient>
                                </defs>

                                @foreach ($yTicks as $tick)
                                    @php($tickY = round($yBase - (($tick / max(1, $yMax)) * $plotHeight), 1))
                                    <line
                                        x1="{{ $xLeft }}"
                                        y1="{{ $tickY }}"
                                        x2="{{ $xRight }}"
                                        y2="{{ $tickY }}"
                                        class="yg-chart-gridline @if ($tick === 0) is-baseline @endif"
                                    />
                                    <text
                                        x="{{ $isRtl ? $xRight + 10 : $xLeft - 10 }}"
                                        y="{{ $tickY + 4 }}"
                                        text-anchor="{{ $isRtl ? 'start' : 'end' }}"
                                        class="yg-chart-tick"
                                    >{{ $tick }}</text>
                                @endforeach

                                @if ($areaPath !== '')
                                    <path d="{{ $areaPath }}" fill="url(#yg-enroll-fill)" />
                                    <path d="{{ $linePath }}" fill="none" class="yg-chart-line" />
                                @endif

                                @foreach ($coords as $idx => $coord)
                                    @if ($coord['showLabel'])
                                        <text
                                            x="{{ $coord['x'] }}"
                                            y="{{ $svgHeight - 10 }}"
                                            text-anchor="{{ $coord['anchor'] }}"
                                            @class(['yg-chart-axis-label', 'is-minor' => $coord['isMinor']])
                                        >{{ $coord['label'] }}</text>
                                    @endif

                                    <circle
                                        cx="{{ $coord['x'] }}"
                                        cy="{{ $coord['y'] }}"
                                        r="4"
                                        class="yg-chart-point"
                                        x-bind:class="{ 'is-active': active === {{ $idx }} }"
                                    />

                                    @php($bandWidth = $pointCount > 1 ? max(24, round($plotWidth / $pointCount, 1)) : $plotWidth)
                                    <rect
                                        x="{{ max(0, round($coord['x'] - ($bandWidth / 2), 1)) }}"
                                        y="{{ $padTop - 8 }}"
                                        width="{{ $bandWidth }}"
                                        height="{{ $plotHeight + 16 }}"
                                        fill="transparent"
                                        class="yg-chart-hit"
                                        @mouseenter="active = {{ $idx }}"
                                    />
                                @endforeach
                            </svg>

                            <template x-if="active !== null && points[active]">
                                <div
                                    class="yg-chart-tooltip"
                                    dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}"
                                    x-bind:style="'left:' + points[active].xPct + '%; top:' + Math.max(10, points[active].yPct - 4) + '%;'"
                                >
                                    <strong x-text="points[active].count + ' ' + @js(__('dashboard.enrollments_unit'))"></strong>
                                    <span x-text="points[active].range"></span>
                                </div>
                            </template>

                            @if ((int) $chart['total'] === 0)
                                <p class="yg-chart-empty-note" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">{{ __('dashboard.enrollments_chart_empty') }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($kpis !== [])
                        @unless ($isAdminOverview && $chart !== null)
                            <header class="yg-overview-card-header">
                                <div class="yg-overview-title-group">
                                    <span class="yg-overview-badge-icon" aria-hidden="true">
                                        <x-filament::icon icon="heroicon-o-chart-bar" />
                                    </span>
                                    <div>
                                        <h2 id="yg-kpi-title">{{ __('dashboard.kpis_title') }}</h2>
                                        <p>{{ __('dashboard.kpis_period', ['days' => $days]) }}</p>
                                    </div>
                                </div>
                            </header>
                        @endunless

                        <div class="yg-metrics-section">
                            @if ($isAdminOverview && $chart !== null)
                                <div class="yg-metrics-caption">
                                    <h3 id="yg-kpi-title">{{ __('dashboard.kpis_title') }}</h3>
                                    <span>{{ __('dashboard.period_summary_title', ['days' => $days]) }}</span>
                                </div>
                            @endif

                            <dl class="yg-overview-metrics" data-count="{{ count($kpis) }}">
                                @foreach ($kpis as $kpi)
                                    <div @class(['yg-metric-cell', 'is-empty' => $kpi['empty']]) data-tone="{{ $kpi['tone'] }}" data-kpi="{{ $kpi['key'] }}">
                                        <dt class="yg-metric-label" title="{{ $kpi['label'] }}">
                                            <x-filament::icon :icon="$kpi['icon']" class="yg-metric-icon" aria-hidden="true" />
                                            <span>{{ $kpi['label'] }}</span>
                                        </dt>
                                        <dd class="yg-metric-value" data-kpi-value="{{ $kpi['raw'] }}">
                                            @foreach ($kpi['lines'] as $line)<bdi dir="ltr">{{ $line }}</bdi>@if (! $loop->last) <span aria-hidden="true">·</span> @endif @endforeach
                                        </dd>
                                        @if (! $kpi['empty'] && $kpi['secondary'] !== null)
                                            <dd class="yg-metric-note">{{ $kpi['secondary_label'] }}: <bdi dir="ltr">{{ $kpi['secondary'] }}</bdi></dd>
                                        @endif
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </section>
            @endif

            @if ($hasAsideCard)
                <section class="yg-overview-card yg-overview-aside" aria-labelledby="yg-queue-title">
                    <header class="yg-overview-card-header is-compact">
                        <div class="yg-overview-title-group">
                            <span class="yg-overview-badge-icon is-warning" aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-flag" />
                            </span>
                            <div>
                                <h2 id="yg-queue-title">{{ __('dashboard.queues_title') }}</h2>
                                <p>{{ __('dashboard.queues_description') }}</p>
                            </div>
                        </div>

                        @if (! $allClear && $pendingQueuesCount > 0)
                            <span class="yg-attention-total">{{ number_format($pendingQueuesCount) }}</span>
                        @endif
                    </header>

                    @if ($allClear)
                        <div class="yg-dashboard-clear is-aside" data-queues-clear>
                            <x-filament::icon icon="heroicon-o-check-circle" aria-hidden="true" />
                            <p><strong data-queue-empty>{{ __('dashboard.queues_all_clear') }}</strong></p>
                        </div>
                        <ul class="yg-attention-list is-clear">
                            @foreach ($queues as $queue)
                                <li>
                                    @php($tag = $queue['url'] ? 'a' : 'div')
                                    <{{ $tag }} @if ($queue['url']) href="{{ $queue['url'] }}" @endif class="yg-attention-row is-zero" data-queue-link="{{ $queue['key'] }}">
                                        <span class="yg-attention-icon" aria-hidden="true">
                                            <x-filament::icon :icon="$queue['icon']" />
                                        </span>
                                        <span class="yg-attention-label">{{ $queue['label'] }}</span>
                                        @if ($queue['url'])
                                            <x-filament::icon icon="heroicon-o-chevron-left" class="yg-directional-icon yg-attention-chevron" aria-hidden="true" />
                                        @endif
                                    </{{ $tag }}>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <ul class="yg-attention-list">
                            @foreach ($queues as $queue)
                                <li>
                                    @php($tag = $queue['url'] ? 'a' : 'div')
                                    <{{ $tag }} @if ($queue['url']) href="{{ $queue['url'] }}" @endif class="yg-attention-row @if ($queue['empty']) is-zero @else is-pending @endif" data-queue="{{ $queue['key'] }}" data-queue-count="{{ $queue['raw'] }}">
                                        <span class="yg-attention-icon" aria-hidden="true">
                                            <x-filament::icon :icon="$queue['icon']" />
                                        </span>
                                        <span class="yg-attention-text">
                                            <span class="yg-attention-label">{{ $queue['label'] }}</span>
                                            @if ($queue['secondary'] !== null && $queue['secondary'] > 0)
                                                <small class="yg-attention-overdue">{{ $queue['secondary_label'] }}: <bdi dir="ltr">{{ $queue['secondary'] }}</bdi></small>
                                            @endif
                                        </span>
                                        @if ($queue['empty'])
                                            <span class="yg-attention-pill is-zero" data-queue-empty>{{ __('dashboard.queue_clear') }}</span>
                                        @else
                                            <span class="yg-attention-pill">
                                                @foreach ($queue['lines'] as $line)<bdi dir="ltr">{{ $line }}</bdi>@if (! $loop->last) · @endif @endforeach
                                            </span>
                                        @endif
                                    </{{ $tag }}>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    @else
        <section class="yg-dashboard-tools">
            <div class="yg-dashboard-empty"><x-filament::icon icon="heroicon-o-chart-bar" aria-hidden="true" /><h3>{{ __('dashboard.empty_title') }}</h3><p>{{ __('dashboard.empty_description') }}</p></div>
        </section>
    @endif

    @if ($isAdminOverview && $recentActivity !== null)
        <section class="yg-overview-card yg-overview-activity" aria-labelledby="yg-recent-activity-title">
            <header class="yg-overview-card-header is-compact">
                <div class="yg-overview-title-group">
                    <span class="yg-overview-badge-icon" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-bolt" />
                    </span>
                    <div>
                        <h2 id="yg-recent-activity-title">{{ __('staff.recent_activity') }}</h2>
                        <p>{{ __('dashboard.recent_activity_description') }}</p>
                    </div>
                </div>

                @if ($auditUrl)
                    <a href="{{ $auditUrl }}" class="yg-overview-link">
                        <span>{{ __('staff.view_all') }}</span>
                        <x-filament::icon icon="heroicon-o-chevron-left" class="yg-directional-icon" aria-hidden="true" />
                    </a>
                @endif
            </header>

            <div class="yg-activity-feed">
                @forelse ($recentActivity as $row)
                    <div class="yg-activity-feed-row">
                        <div class="yg-activity-feed-main">
                            <x-ui.avatar :name="$row['actor']" class="yg-person-avatar" />
                            <div class="yg-activity-feed-copy">
                                <p>
                                    <strong>{{ $row['actor'] }}</strong>
                                    <span>{{ $row['event'] }}</span>
                                </p>
                                <span class="yg-activity-subject">
                                    {{ $row['subject'] }}@if ($row['subjectId']) <bdi dir="ltr">#{{ $row['subjectId'] }}</bdi>@endif
                                </span>
                            </div>
                        </div>
                        @if ($row['time'] !== '')
                            <time class="yg-activity-feed-time" datetime="{{ $row['iso'] }}">{{ $row['time'] }}</time>
                        @endif
                    </div>
                @empty
                    <p class="yg-workspace-empty">{{ __('staff.no_recent_activity') }}</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
