<x-filament-panels::page>
    @include('filament.review.styles')
    <style>
        .yg-tc-banner { display: flex; gap: .75rem; align-items: flex-start; padding: .75rem 1rem; border: 1px dashed var(--yg-color-primary); border-radius: var(--yg-radius-card); background: var(--yg-color-page); }
        .yg-tc-banner strong { font-weight: 700; }
        .yg-tc-field { display: grid; gap: .375rem; }
        .yg-tc-field label { font-weight: 600; }
        .yg-tc-row { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); }
        .yg-tc-quote { border-inline-start: 3px solid var(--yg-color-primary); padding-inline-start: .75rem; }
        .yg-tc-error { color: var(--yg-color-danger, currentColor); font-weight: 600; }
    </style>

    <div class="yg-rv" data-test-console>
        <div class="yg-tc-banner" role="note" data-test-console-banner>
            <x-filament::icon icon="heroicon-o-beaker" class="h-6 w-6" />
            <div>
                <strong>{{ __('ai.console.banner_title') }}</strong>
                <p class="yg-rv-meta">{{ __('ai.console.banner_body') }}</p>
                <p class="yg-rv-meta">{{ __('ai.console.provider_note', ['provider' => $provider]) }}</p>
            </div>
        </div>

        @if($lessons === [])
            <section class="yg-rv-card" data-test-console-empty>
                <p>{{ __('ai.console.no_lessons') }}</p>
            </section>
        @else
            <div class="yg-rv-grid">
                <section class="yg-rv-card" aria-label="{{ __('ai.console.ask_title') }}">
                    <h3 class="yg-rv-title">{{ __('ai.console.ask_title') }}</h3>
                    <form wire:submit="ask" class="yg-rv" data-test-console-form>
                        <div class="yg-tc-field">
                            <label for="tc-lesson">{{ __('ai.console.lesson') }}</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select id="tc-lesson" wire:model="lessonId">
                                    <option value="">{{ __('ai.console.lesson_placeholder') }}</option>
                                    @foreach($lessons as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                        <div class="yg-tc-row">
                            <div class="yg-tc-field">
                                <label for="tc-locale">{{ __('ai.console.language') }}</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select id="tc-locale" wire:model="locale">
                                        <option value="en">{{ __('ai.console.lang_en') }}</option>
                                        <option value="ar">{{ __('ai.console.lang_ar') }}</option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </div>
                            <div class="yg-tc-field">
                                <label for="tc-tier">{{ __('ai.console.tier') }}</label>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select id="tc-tier" wire:model="tier">
                                        @foreach(['kids', 'teens', 'adults'] as $tier)<option value="{{ $tier }}">{{ __('ai.console.tiers.'.$tier) }}</option>@endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </div>
                        </div>
                        <div class="yg-tc-field">
                            <label for="tc-question">{{ __('ai.question') }}</label>
                            <x-filament::input.wrapper>
                                <textarea id="tc-question" class="fi-input" rows="3" dir="auto" maxlength="{{ $maxChars }}" wire:model="question" required></textarea>
                            </x-filament::input.wrapper>
                            <span class="yg-rv-meta">{{ __('ai.characters', ['count' => mb_strlen($question), 'max' => $maxChars]) }}</span>
                        </div>
                        @if($error)<p class="yg-tc-error" role="alert" data-test-console-error>{{ $error }}</p>@endif
                        <div>
                            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="ask">
                                <span wire:loading.remove wire:target="ask">{{ __('ai.console.ask') }}</span>
                                <span wire:loading wire:target="ask">{{ __('ai.console.asking') }}</span>
                            </x-filament::button>
                        </div>
                    </form>
                </section>

                <section class="yg-rv-card" aria-live="polite" aria-label="{{ __('ai.console.result') }}" data-test-console-result>
                    <h3 class="yg-rv-title">{{ __('ai.console.result') }} <span class="yg-rv-badge">{{ __('ai.console.badge') }}</span></h3>
                    @if($result === null)
                        <p class="yg-rv-meta">{{ __('ai.console.empty_result') }}</p>
                    @else
                        <div>
                            <span class="yg-rv-badge" data-test-console-status="{{ $result['outcome'] }}">{{ __('ai.console.outcomes.'.$result['outcome']) }}</span>
                        </div>
                        <div class="yg-rv-text" dir="auto" data-test-console-answer>{{ $result['message'] }}</div>

                        <dl class="yg-rv-terms">
                            <div><dt>{{ __('ai.console.pages') }}</dt><dd data-test-console-pages>
                                @forelse($result['pages'] as $page)<span class="yg-rv-badge">{{ __('ai.source_page', ['page' => $page]) }}</span> @empty - @endforelse
                            </dd></div>
                            <div><dt>{{ __('ai.console.language_used') }}</dt><dd>{{ __('ai.console.lang_'.$result['language']) }}</dd></div>
                            <div><dt>{{ __('ai.console.tier_used') }}</dt><dd>{{ __('ai.console.tiers.'.$result['tier']) }}</dd></div>
                        </dl>

                        <h4 class="yg-rv-title">{{ __('ai.console.quote') }}</h4>
                        @if($result['quote'] !== '')
                            <p class="yg-tc-quote" dir="auto" data-test-console-quote>{{ $result['quote'] }}</p>
                        @else
                            <p class="yg-rv-meta">{{ __('ai.console.no_quote') }}</p>
                        @endif

                        <h4 class="yg-rv-title">{{ __('ai.console.guards') }}</h4>
                        <ul class="yg-rv-list" data-test-console-guards>
                            <li class="yg-rv-item">{{ __('ai.console.input_guard') }}: {{ $result['input_guard'] === null ? __('ai.console.passed') : __('ai.console.guard_names.'.$result['input_guard']) }}</li>
                            <li class="yg-rv-item">{{ __('ai.console.pii') }}: {{ $result['pii'] === [] ? __('ai.console.none') : implode(', ', array_map(fn ($kind) => __('ai.console.pii_kinds.'.$kind), $result['pii'])) }}</li>
                            <li class="yg-rv-item">{{ __('ai.console.provider_called') }}: {{ $result['provider_called'] ? __('ai.console.yes') : __('ai.console.no') }}@if($result['attempts'] > 1) ({{ __('ai.console.attempts', ['count' => $result['attempts']]) }})@endif</li>
                            <li class="yg-rv-item">{{ __('ai.console.quote_verified') }}: {{ $result['quote_verified'] ? __('ai.console.yes') : __('ai.console.no') }}</li>
                            <li class="yg-rv-item">{{ __('ai.console.output_check') }}: {{ $result['provider_failure'] ? __('ai.console.provider_failures.'.$result['provider_failure']) : ($result['output_failure'] ? __('ai.console.failures.'.$result['output_failure']) : __('ai.console.passed')) }}@if($result['trimmed']) · {{ __('ai.console.trimmed') }}@endif</li>
                        </ul>
                        <p class="yg-rv-meta">{{ __('ai.console.masked_question') }}</p>
                        <div class="yg-rv-text" dir="auto">{{ $result['masked_question'] }}</div>
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
