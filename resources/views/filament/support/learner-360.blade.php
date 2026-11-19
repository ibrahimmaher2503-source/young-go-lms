<x-filament-panels::page>
    @include('filament.ai.styles')

    <div class="yg-rv" data-learner-360>
        @if($record === null)
            <section class="yg-rv-card" aria-label="{{ __('support.search_title') }}" data-section="search">
                <h3 class="yg-rv-title">{{ __('support.search_title') }}</h3>
                <p class="yg-rv-meta">{{ __('support.search_help') }}</p>
                <div class="yg-ai-field">
                    <label for="learner-search">{{ __('support.search_label') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="learner-search" type="search" wire:model.live.debounce.400ms="search" autocomplete="off" />
                    </x-filament::input.wrapper>
                </div>
                @if($results !== [])
                    <ul class="yg-rv-list" data-search-results>
                        @foreach($results as $result)
                            <li class="yg-rv-item">
                                <a class="yg-rv-link" href="{{ \App\Filament\Pages\LearnerThreeSixtyPage::getUrl(['learner' => $result['id']]) }}">{{ $result['name'] }}</a>
                                <span class="yg-rv-meta">@if($result['username'])<span dir="ltr">{{ $result['username'] }}</span> · @endif<span dir="ltr">{{ $result['email'] ?? __('support.no_email') }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                @elseif(mb_strlen(trim($search)) >= 2)
                    <p class="yg-rv-meta" data-search-empty>{{ __('support.search_empty') }}</p>
                @endif
            </section>
        @else
            @php
                $profile = $record['profile'];
                $dash = '—';
            @endphp

            <section class="yg-rv-card" aria-label="{{ __('support.profile') }}" data-section="profile">
                <div class="yg-ai-row">
                    <div>
                        <h3 class="yg-rv-title">{{ $profile['display_name'] ?: $profile['name'] }}</h3>
                        <p class="yg-rv-meta">
                            @if($record['minor'])<span class="yg-rv-badge" data-minor>{{ __('support.minor') }}</span>@endif
                            <span class="yg-rv-badge">{{ __('support.account_status.'.$profile['status']) }}</span>
                        </p>
                    </div>
                    <div class="yg-ai-actions">
                        <a class="yg-rv-link" href="{{ \App\Filament\Pages\LearnerThreeSixtyPage::getUrl() }}">{{ __('support.back_to_search') }}</a>
                        @if($refusal === null)
                            <button type="button" class="yg-rv-btn" wire:click="mountAction('startViewAs')" data-action="view-as-start">{{ __('support.view_as_start') }}</button>
                        @else
                            <span class="yg-rv-meta" data-view-as-refusal="{{ $refusal->value }}">{{ __('support.view_as_unavailable', ['reason' => $refusal->label()]) }}</span>
                        @endif
                    </div>
                </div>
                <p class="yg-rv-meta">{{ __('support.view_as_notice', ['minutes' => $ttl]) }}</p>
                <dl class="yg-rv-terms">
                    <div><dt>{{ __('support.f_username') }}</dt><dd dir="ltr">{{ $profile['username'] ?: $dash }}</dd></div>
                    <div>
                        <dt>{{ __('support.f_email') }}</dt>
                        <dd dir="ltr">@if($profile['contact_hidden']){{ __('support.hidden_child') }}@else{{ $profile['email'] ?: $dash }}@endif</dd>
                    </div>
                    <div>
                        <dt>{{ __('support.f_phone') }}</dt>
                        <dd dir="ltr">@if($profile['contact_hidden']){{ __('support.hidden_child') }}@else{{ $profile['phone'] ?: $dash }}@endif</dd>
                    </div>
                    @if($profile['birth_year'])<div><dt>{{ __('support.f_birth_year') }}</dt><dd>{{ $profile['birth_year'] }}</dd></div>@endif
                    <div><dt>{{ __('support.f_locale') }}</dt><dd>{{ $profile['locale'] ? strtoupper($profile['locale']) : $dash }}</dd></div>
                    <div><dt>{{ __('support.f_timezone') }}</dt><dd dir="ltr">{{ $profile['timezone'] ?: $dash }}</dd></div>
                    <div><dt>{{ __('support.f_joined') }}</dt><dd>{{ $profile['joined_at'] ?: $dash }}</dd></div>
                    <div><dt>{{ __('support.f_last_login') }}</dt><dd>{{ $profile['last_login_at'] ?: $dash }}</dd></div>
                </dl>
                @if($profile['masked'] && ! $profile['contact_hidden'])<p class="yg-rv-meta" data-masked-note>{{ __('support.masked_note') }}</p>@endif
            </section>

            <section class="yg-rv-card" aria-label="{{ __('support.guardians') }}" data-section="guardians">
                <h3 class="yg-rv-title">{{ __('support.guardians') }}</h3>
                @if($record['guardians'] === [])
                    <p class="yg-rv-meta">{{ __('support.none_guardians') }}</p>
                @else
                    <ul class="yg-rv-list">
                        @foreach($record['guardians'] as $link)
                            <li class="yg-rv-item">
                                <span><strong>{{ $link['name'] }}</strong> · {{ __('support.relation.'.$link['relation']) }} · {{ $link['verified'] ? __('support.link_verified') : __('support.link_pending') }}</span>
                                @if($link['email'])<span class="yg-rv-meta" dir="ltr">{{ $link['email'] }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="yg-rv-card" aria-label="{{ __('support.access') }}" data-section="access">
                <h3 class="yg-rv-title">{{ __('support.access') }}</h3>
                @if($record['access'] === [])
                    <p class="yg-rv-meta">{{ __('support.none_access') }}</p>
                @else
                    <div class="yg-ai-scroll"><table class="yg-ai-table">
                        <thead><tr><th>{{ __('support.c_course') }}</th><th>{{ __('support.c_source') }}</th><th>{{ __('support.c_state') }}</th><th>{{ __('support.c_from') }}</th><th>{{ __('support.c_until') }}</th><th>{{ __('support.c_now') }}</th></tr></thead>
                        <tbody>
                        @foreach($record['access'] as $row)
                            <tr>
                                <td>{{ $row['course'] }}</td>
                                <td data-source><span>{{ __('support.sources.'.$row['source']) }}</span>@if($row['reference_label'])<span class="yg-rv-meta" data-access-reference>{{ $row['reference_label'] }}</span>@endif</td>
                                <td>{{ __('support.access_state.'.$row['state']) }}</td>
                                <td>{{ $row['starts_at']->isoFormat('LL') }}</td>
                                <td>{{ $row['ends_at']?->isoFormat('LL') ?? $dash }}</td>
                                <td>{{ $row['allowed_now'] ? __('support.yes') : ($row['decision'] ?? __('support.no')) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </section>

            @if($record['progress'] !== null)
                <section class="yg-rv-card" aria-label="{{ __('support.progress') }}" data-section="progress">
                    <h3 class="yg-rv-title">{{ __('support.progress') }}</h3>
                    @if($record['progress'] === [])
                        <p class="yg-rv-meta">{{ __('support.none_progress') }}</p>
                    @else
                        <div class="yg-ai-scroll"><table class="yg-ai-table">
                            <thead><tr><th>{{ __('support.c_course') }}</th><th>{{ __('support.c_state') }}</th><th>{{ __('support.c_percent') }}</th><th>{{ __('support.c_lessons') }}</th></tr></thead>
                            <tbody>
                            @foreach($record['progress'] as $row)
                                <tr><td>{{ $row['course'] }}</td><td>{{ __('support.progress_status.'.$row['status']) }}</td><td class="yg-ai-num">{{ $row['percent'] }}%</td><td class="yg-ai-num">{{ $row['done'] }} / {{ $row['total'] }}</td></tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </section>
            @endif

            <section class="yg-rv-card" aria-label="{{ __('support.orders') }}" data-section="orders">
                <h3 class="yg-rv-title">{{ __('support.orders') }}</h3>
                @if($record['orders'] === [])
                    <p class="yg-rv-meta">{{ __('support.none_orders') }}</p>
                @else
                    <ul class="yg-rv-list">
                        @foreach($record['orders'] as $order)
                            <li class="yg-rv-item">
                                <span><strong dir="ltr">{{ $order['reference'] }}</strong> · {{ __('support.order_status.'.$order['status']) }} · <span class="yg-ai-num">{{ $order['total'] }}</span>@if($order['paid_by_other']) · {{ __('support.paid_by_guardian') }}@endif</span>
                                <span class="yg-rv-meta">{{ $order['paid_at'] ?? __('support.not_paid') }}@if($order['items'] !== []) · {{ implode(', ', $order['items']) }}@endif</span>
                                @if($order['attempts'] !== null)
                                    @foreach($order['attempts'] as $attempt)
                                        <span class="yg-rv-meta" data-attempt>{{ __('support.attempt') }}: {{ $attempt['gateway'] }} · {{ __('support.attempt_status.'.$attempt['status']) }} · {{ $attempt['amount'] }}</span>
                                    @endforeach
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if(collect($record['orders'])->contains(fn ($order) => ($order['refunds'] ?? []) !== []))
                <section class="yg-rv-card" aria-label="{{ __('support.refunds') }}" data-section="refunds">
                    <h3 class="yg-rv-title">{{ __('support.refunds') }}</h3>
                    <ul class="yg-rv-list">
                        @foreach($record['orders'] as $order)
                            @foreach($order['refunds'] ?? [] as $refund)
                                <li class="yg-rv-item"><span><strong dir="ltr">{{ $refund['reference'] }}</strong> · {{ __('support.refund_status.'.$refund['status']) }} · <span class="yg-ai-num">{{ $refund['amount'] }}</span></span><span class="yg-rv-meta">{{ __('support.c_order') }} <span dir="ltr">{{ $order['reference'] }}</span> · {{ $refund['at'] }}</span></li>
                            @endforeach
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="yg-rv-card" aria-label="{{ __('support.certificates') }}" data-section="certificates">
                <h3 class="yg-rv-title">{{ __('support.certificates') }}</h3>
                @if($record['certificates'] === [])
                    <p class="yg-rv-meta">{{ __('support.none_certificates') }}</p>
                @else
                    <ul class="yg-rv-list">
                        @foreach($record['certificates'] as $certificate)
                            <li class="yg-rv-item"><span><strong dir="ltr">{{ $certificate['number'] }}</strong> · {{ $certificate['course'] }}</span><span class="yg-rv-meta">{{ $certificate['issued_at'] }} · {{ $certificate['valid'] ? __('support.cert_valid') : __('support.cert_revoked') }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="yg-rv-card" aria-label="{{ __('support.help') }}" data-section="help">
                <h3 class="yg-rv-title">{{ __('support.help') }}</h3>
                @if($record['help'] === [])
                    <p class="yg-rv-meta">{{ __('support.none_help') }}</p>
                @else
                    <ul class="yg-rv-list">
                        @foreach($record['help'] as $request)
                            <li class="yg-rv-item"><span><strong>#{{ $request['id'] }}</strong> · {{ $request['course'] }} · {{ $request['status'] }}@if($request['overdue']) · {{ __('support.overdue') }}@endif</span><span class="yg-rv-meta">{{ $request['created_at'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="yg-rv-card" aria-label="{{ __('support.notices') }}" data-section="notices">
                <h3 class="yg-rv-title">{{ __('support.notices') }}</h3>
                <p class="yg-rv-meta">{{ __('support.notices_note') }}</p>
                @if($record['notices'] === [])
                    <p class="yg-rv-meta">{{ __('support.none_notices') }}</p>
                @else
                    <ul class="yg-rv-list">
                        @foreach($record['notices'] as $notice)
                            <li class="yg-rv-item"><span>{{ $notice['label'] }}</span><span class="yg-rv-meta">{{ $notice['at'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if($record['privacy'] !== null)
                <section class="yg-rv-card" aria-label="{{ __('support.privacy') }}" data-section="privacy">
                    <h3 class="yg-rv-title">{{ __('support.privacy') }}</h3>
                    @if($record['privacy'] === [])
                        <p class="yg-rv-meta">{{ __('support.none_privacy') }}</p>
                    @else
                        <ul class="yg-rv-list">
                            @foreach($record['privacy'] as $request)
                                <li class="yg-rv-item"><span>{{ $request['type'] }} · {{ $request['status'] }}</span><span class="yg-rv-meta">{{ $request['created_at'] }}@if($request['completed_at']) · {{ $request['completed_at'] }}@endif</span></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if($record['devices'] !== null)
                <section class="yg-rv-card" aria-label="{{ __('support.devices') }}" data-section="devices">
                    <h3 class="yg-rv-title">{{ __('support.devices') }}</h3>
                    @if($record['devices'] === [])
                        <p class="yg-rv-meta">{{ __('support.none_devices') }}</p>
                    @else
                        <ul class="yg-rv-list">
                            @foreach($record['devices'] as $device)
                                <li class="yg-rv-item"><span>{{ $device['label'] ?: __('support.unnamed_device') }}</span><span class="yg-rv-meta">{{ $device['last_seen_at'] ?? $dash }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @elseif($record['minor'])
                <p class="yg-rv-meta" data-devices-hidden>{{ __('support.devices_hidden_child') }}</p>
            @endif

            @if($record['audit'] !== null)
                <section class="yg-rv-card" aria-label="{{ __('support.audit') }}" data-section="audit">
                    <h3 class="yg-rv-title">{{ __('support.audit') }}</h3>
                    @if($record['audit'] === [])
                        <p class="yg-rv-meta">{{ __('support.none_audit') }}</p>
                    @else
                        <ul class="yg-rv-list">
                            @foreach($record['audit'] as $entry)
                                <li class="yg-rv-item"><span>{{ $entry['event'] }}</span><span class="yg-rv-meta">{{ $entry['actor'] }} · {{ $entry['at'] }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    <h4 class="yg-rv-title">{{ __('support.view_as_history') }}</h4>
                    @forelse($record['view_as'] as $session)
                        <p class="yg-rv-item" data-view-as-row><span>{{ $session['staff'] }} · {{ $session['started_at'] }} → {{ $session['ended_at'] ?? __('support.still_open') }}@if($session['cause']) ({{ __('support.end_cause.'.$session['cause']) }})@endif · {{ __('support.page_views', ['count' => $session['page_views']]) }}</span><span class="yg-rv-meta">{{ $session['reason'] }}</span></p>
                    @empty
                        <p class="yg-rv-meta">{{ __('support.none_view_as') }}</p>
                    @endforelse
                </section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
