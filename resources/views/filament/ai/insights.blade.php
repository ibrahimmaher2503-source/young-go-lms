<x-filament-panels::page>
    @include('filament.ai.styles')

    <div class="yg-rv" data-assistant-insights>
        <section class="yg-rv-card" aria-label="{{ __('ai_admin.insights.pick') }}">
            <p class="yg-rv-meta">{{ __('ai_admin.insights.privacy_note') }}</p>
            <div class="yg-ai-filters">
                <div class="yg-ai-field">
                    <label for="ins-lesson">{{ __('ai_admin.insights.lesson') }}</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="ins-lesson" wire:model.live="lessonId">
                            <option value="">{{ __('ai_admin.insights.lesson_placeholder') }}</option>
                            @foreach($lessons as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                <div class="yg-ai-field">
                    <label for="ins-from">{{ __('ai_admin.usage.from') }}</label>
                    <x-filament::input.wrapper><x-filament::input type="date" id="ins-from" wire:model.live="from" /></x-filament::input.wrapper>
                </div>
                <div class="yg-ai-field">
                    <label for="ins-to">{{ __('ai_admin.usage.to') }}</label>
                    <x-filament::input.wrapper><x-filament::input type="date" id="ins-to" wire:model.live="to" /></x-filament::input.wrapper>
                </div>
            </div>
        </section>

        @if($lessons === [])
            <section class="yg-rv-card" data-insights-empty><p>{{ __('ai_admin.insights.no_lessons') }}</p></section>
        @elseif($insights === null)
            <section class="yg-rv-card"><p class="yg-rv-meta">{{ __('ai_admin.insights.choose') }}</p></section>
        @else
            <dl class="yg-ai-tiles" data-insight-totals>
                <div class="yg-ai-tile"><dt>{{ __('ai_admin.insights.questions') }}</dt><dd class="yg-ai-num" data-insight="questions">{{ $insights['questions'] }}</dd></div>
                <div class="yg-ai-tile"><dt>{{ __('ai_admin.insights.answered') }}</dt><dd class="yg-ai-num" data-insight="answered">{{ $insights['answered'] }}</dd></div>
                <div class="yg-ai-tile"><dt>{{ __('ai_admin.insights.not_in_source') }}</dt><dd class="yg-ai-num" data-insight="not_in_source">{{ $insights['not_in_source'] }}</dd></div>
                <div class="yg-ai-tile"><dt>{{ __('ai_admin.insights.not_understood') }}</dt><dd class="yg-ai-num" data-insight="not_understood">{{ $insights['not_understood'] }}</dd></div>
                <div class="yg-ai-tile"><dt>{{ __('ai_admin.insights.simpler') }}</dt><dd class="yg-ai-num" data-insight="simpler">{{ $insights['simpler'] }}</dd></div>
            </dl>

            <div class="yg-rv-grid">
                <section class="yg-rv-card" aria-label="{{ __('ai_admin.insights.gaps') }}" data-section="gaps">
                    <h3 class="yg-rv-title">{{ __('ai_admin.insights.gaps') }}</h3>
                    <p class="yg-rv-meta">{{ __('ai_admin.insights.gaps_help') }}</p>
                    @if($insights['gaps'] === [])
                        <p class="yg-rv-meta">{{ __('ai_admin.insights.none_yet') }}</p>
                    @else
                        <ul class="yg-rv-list">
                            @foreach($insights['gaps'] as $gap)
                                <li class="yg-rv-item" data-gap><span dir="auto">{{ $gap['question'] }}</span><span class="yg-rv-meta">{{ __('ai_admin.insights.times', ['count' => $gap['count']]) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <div class="yg-rv">
                    <section class="yg-rv-card" aria-label="{{ __('ai_admin.insights.topics') }}" data-section="topics">
                        <h3 class="yg-rv-title">{{ __('ai_admin.insights.topics') }}</h3>
                        @if($insights['topics'] === [])
                            <p class="yg-rv-meta">{{ __('ai_admin.insights.none_yet') }}</p>
                        @else
                            <ul class="yg-rv-list">
                                @foreach($insights['topics'] as $topic)
                                    <li class="yg-rv-item" data-topic><span class="yg-ai-row"><span dir="auto">{{ $topic['term'] }}</span><strong class="yg-ai-num">{{ $topic['count'] }}</strong></span></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section class="yg-rv-card" aria-label="{{ __('ai_admin.insights.pages') }}" data-section="pages">
                        <h3 class="yg-rv-title">{{ __('ai_admin.insights.pages') }}</h3>
                        @if($insights['pages'] === [])
                            <p class="yg-rv-meta">{{ __('ai_admin.insights.none_yet') }}</p>
                        @else
                            <ul class="yg-rv-list">
                                @foreach($insights['pages'] as $page)
                                    <li class="yg-rv-item" data-cited-page="{{ $page['page'] }}"><span class="yg-ai-row"><span>{{ __('ai.source_page', ['page' => $page['page']]) }}</span><strong class="yg-ai-num">{{ $page['count'] }}</strong></span></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
