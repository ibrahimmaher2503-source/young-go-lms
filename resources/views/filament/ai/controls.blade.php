<x-filament-panels::page>
    @include('filament.ai.styles')

    @php
        $money = static fn (?int $minor): string => $minor === null ? __('ai_admin.controls.no_limit') : number_format($minor / 100, 2).' '.($settings->budget_currency ?? '');
    @endphp

    <div class="yg-rv" data-assistant-controls>
        <section class="yg-rv-card" aria-label="{{ __('ai_admin.controls.global_title') }}" data-section="global">
            <div class="yg-ai-row">
                <div>
                    <h3 class="yg-rv-title">{{ __('ai_admin.controls.global_title') }}</h3>
                    <p class="yg-rv-meta">{{ __('ai_admin.controls.global_help') }}</p>
                </div>
                <div class="yg-ai-actions">
                    <span class="yg-rv-badge" data-global-state="{{ $on ? 'on' : 'off' }}"><span class="yg-ai-dot" data-state="{{ $on ? 'ok' : 'todo' }}" aria-hidden="true"></span>&nbsp;{{ $on ? __('ai_admin.controls.state_on') : __('ai_admin.controls.state_off') }}</span>
                    @if($on)
                        <button type="button" class="yg-rv-btn" wire:click="mountAction('disable')" data-action="disable">{{ __('ai_admin.controls.disable') }}</button>
                    @else
                        <button type="button" class="yg-rv-btn" wire:click="mountAction('enable')" data-action="enable">{{ __('ai_admin.controls.enable') }}</button>
                    @endif
                </div>
            </div>
        </section>

        <div class="yg-rv-grid">
            <section class="yg-rv-card" aria-label="{{ __('ai_admin.readiness.title') }}" data-section="readiness">
                <h3 class="yg-rv-title">{{ __('ai_admin.readiness.title') }}</h3>
                <p class="yg-rv-meta">{{ __('ai_admin.readiness.help') }}</p>
                <ul class="yg-rv-list">
                    @foreach($readiness['checks'] as $check)
                        <li class="yg-ai-check" data-check="{{ $check['key'] }}" data-state="{{ $check['state'] }}">
                            <span class="yg-ai-dot" data-state="{{ $check['state'] }}" aria-hidden="true"></span>
                            <span>
                                <strong>{{ __('ai_admin.readiness.checks.'.$check['key'].'.label') }}</strong>
                                <span class="yg-rv-meta" style="display:block">{{ __('ai_admin.readiness.checks.'.$check['key'].'.'.$check['state'], array_map(static fn ($value) => $value ?? '-', $check['params'])) }}</span>
                                <span class="yg-rv-badge">{{ __('ai_admin.readiness.state.'.$check['state']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="yg-rv-card" aria-label="{{ __('ai_admin.readiness.lessons_title') }}" data-section="lessons">
                <h3 class="yg-rv-title">{{ __('ai_admin.readiness.lessons_title') }}</h3>
                <p class="yg-rv-meta">{{ __('ai_admin.readiness.lessons_help', ['total' => $readiness['lessons']['total']]) }}</p>
                <dl class="yg-rv-terms">
                    @foreach(['ready', 'awaiting_approval', 'processing', 'failed', 'none'] as $state)
                        <div data-lesson-state="{{ $state }}"><dt>{{ __('media.readiness.'.$state) }}</dt><dd class="yg-ai-num">{{ $readiness['lessons'][$state] }}</dd></div>
                    @endforeach
                </dl>
            </section>
        </div>

        <section class="yg-rv-card" aria-label="{{ __('ai_admin.health.title') }}" data-section="health">
            <h3 class="yg-rv-title">{{ __('ai_admin.health.title') }}</h3>
            <p class="yg-rv-meta">{{ __('ai_admin.health.help') }}</p>
            <div class="yg-ai-scroll">
                <table class="yg-ai-table">
                    <thead><tr>
                        <th scope="col">{{ __('ai_admin.health.route') }}</th>
                        <th scope="col">{{ __('ai_admin.health.provider') }}</th>
                        <th scope="col">{{ __('ai_admin.health.circuit') }}</th>
                        <th scope="col">{{ __('ai_admin.health.health') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('ai_admin.controls.reset_circuit') }}</span></th>
                    </tr></thead>
                    <tbody>
                    @foreach($health['routes'] as $route)
                        <tr data-route="{{ $route['key'] }}" data-circuit="{{ $route['circuit'] }}">
                            <td>{{ __('ai_admin.health.roles.'.$route['role']) }}</td>
                            <td>{{ __('ai_admin.providers.'.$route['key']) }}</td>
                            <td>
                                <span class="yg-ai-dot" data-state="{{ $route['circuit'] === 'closed' ? 'ok' : ($route['circuit'] === 'open' ? 'open' : 'todo') }}" aria-hidden="true"></span>
                                {{ __('ai_admin.health.circuits.'.$route['circuit']) }}
                                @if($route['retry_at'] && $route['circuit'] !== 'closed')<span class="yg-rv-meta">· {{ __('ai_admin.health.retry_at', ['time' => \Illuminate\Support\Carbon::createFromTimestamp($route['retry_at'])->isoFormat('LTS')]) }}</span>@endif
                                @if($route['failures'] > 0)<span class="yg-rv-meta">· {{ __('ai_admin.health.failures', ['count' => $route['failures']]) }}</span>@endif
                            </td>
                            <td>
                                <span class="yg-ai-dot" data-state="{{ $route['healthy'] ? 'ok' : 'down' }}" aria-hidden="true"></span>
                                {{ $route['healthy'] ? __('ai_admin.health.healthy') : __('ai_admin.health.unhealthy') }}
                            </td>
                            <td>
                                @if($route['circuit'] !== 'closed' || $route['failures'] > 0)
                                    <button type="button" class="yg-rv-btn" wire:click="mountAction('resetCircuit', { route: '{{ $route['key'] }}' })" data-reset-circuit="{{ $route['key'] }}">{{ __('ai_admin.controls.reset_circuit') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <dl class="yg-rv-terms" data-health-day>
                <div><dt>{{ __('ai_admin.health.requests_24h') }}</dt><dd class="yg-ai-num">{{ $health['day']['requests'] }}</dd></div>
                <div><dt>{{ __('ai_admin.health.errors_24h') }}</dt><dd class="yg-ai-num">{{ $health['day']['errors'] }}</dd></div>
                <div><dt>{{ __('ai_admin.health.error_rate') }}</dt><dd class="yg-ai-num">{{ $health['day']['error_rate'] }}%</dd></div>
                <div><dt>{{ __('ai_admin.health.last_error') }}</dt><dd>{{ $health['day']['last_error_at'] ? \Illuminate\Support\Carbon::parse($health['day']['last_error_at'])->isoFormat('lll') : '-' }}</dd></div>
            </dl>
        </section>

        <div class="yg-rv-grid">
            <section class="yg-rv-card" aria-label="{{ __('ai_admin.controls.provider_title') }}" data-section="provider">
                <div class="yg-ai-row">
                    <h3 class="yg-rv-title">{{ __('ai_admin.controls.provider_title') }}</h3>
                    <button type="button" class="yg-rv-btn" wire:click="mountAction('provider')" data-action="provider">{{ __('ai_admin.controls.edit') }}</button>
                </div>
                <dl class="yg-rv-terms">
                    <div><dt>{{ __('ai_admin.fields.provider') }}</dt><dd>{{ __('ai_admin.providers.'.$provider) }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.fallback_provider') }}</dt><dd>{{ $fallback ? __('ai_admin.providers.'.$fallback) : __('ai_admin.controls.none') }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.model') }}</dt><dd><bdi dir="ltr">{{ $settings->model ?? '-' }}</bdi></dd></div>
                    <div><dt>{{ __('ai_admin.fields.prompt_version') }}</dt><dd>{{ $prompt ?? '-' }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.breaker_failures') }}</dt><dd class="yg-ai-num">{{ $settings->breaker_failures }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.breaker_cool_off_seconds') }}</dt><dd class="yg-ai-num">{{ $settings->breaker_cool_off_seconds }}</dd></div>
                </dl>
                <p class="yg-rv-meta">{{ __('ai_admin.controls.provider_open') }}</p>
            </section>

            <section class="yg-rv-card" aria-label="{{ __('ai_admin.controls.quotas_title') }}" data-section="quotas">
                <div class="yg-ai-row">
                    <h3 class="yg-rv-title">{{ __('ai_admin.controls.quotas_title') }}</h3>
                    <button type="button" class="yg-rv-btn" wire:click="mountAction('quotas')" data-action="quotas">{{ __('ai_admin.controls.edit') }}</button>
                </div>
                <dl class="yg-rv-terms">
                    <div><dt>{{ __('ai_admin.fields.questions_per_student_day') }}</dt><dd class="yg-ai-num">{{ $settings->questions_per_student_day }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.questions_per_student_lesson_day') }}</dt><dd class="yg-ai-num">{{ $settings->questions_per_student_lesson_day }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.monthly_budget_minor') }}</dt><dd class="yg-ai-num">{{ $money($settings->monthly_budget_minor) }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.course_monthly_budget_minor') }}</dt><dd class="yg-ai-num">{{ $money($settings->course_monthly_budget_minor) }}</dd></div>
                    <div><dt>{{ __('ai_admin.fields.budget_alert_percent') }}</dt><dd class="yg-ai-num">{{ $settings->budget_alert_percent }}%</dd></div>
                </dl>
            </section>
        </div>

        <section class="yg-rv-card" aria-label="{{ __('ai_admin.controls.retention_title') }}" data-section="retention">
            <div class="yg-ai-row">
                <h3 class="yg-rv-title">{{ __('ai_admin.controls.retention_title') }}</h3>
                <button type="button" class="yg-rv-btn" wire:click="mountAction('retention')" data-action="retention">{{ __('ai_admin.controls.edit') }}</button>
            </div>
            <dl class="yg-rv-terms">
                <div><dt>{{ __('ai_admin.fields.message_retention_days') }}</dt><dd class="yg-ai-num">{{ $settings->message_retention_days }}</dd></div>
                <div><dt>{{ __('ai_admin.fields.usage_retention_months') }}</dt><dd class="yg-ai-num">{{ $settings->usage_retention_months }}</dd></div>
            </dl>
            <p class="yg-rv-meta">{{ __('ai_admin.controls.retention_note') }}</p>
        </section>
    </div>
</x-filament-panels::page>
