{{-- Panel banner while a view-as session is open: the panel is read-only until Stop (ED-027). --}}
@php($session = app(\App\Domain\Support\ViewAsState::class)->current())
@if($session)
    @include('filament.ai.styles')
    <div role="status" class="yg-rv-card" data-view-as-banner style="border-width: 2px; border-color: var(--yg-color-primary);">
        <div class="yg-ai-row">
            <div>
                <p class="yg-rv-title">{{ __('support.banner', ['name' => $session->learner?->display_name ?: $session->learner?->name]) }}</p>
                <p class="yg-rv-meta">{{ __('support.banner_limits', ['time' => $session->expires_at->isoFormat('LT')]) }} {{ __('support.panel_readonly') }}</p>
            </div>
            <form method="POST" action="{{ route('support.view-as.stop') }}">
                @csrf
                <button type="submit" class="yg-rv-btn" data-view-as-stop>{{ __('support.stop') }}</button>
            </form>
        </div>
    </div>
@endif
