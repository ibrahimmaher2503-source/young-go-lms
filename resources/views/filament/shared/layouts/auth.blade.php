@php
    $livewire ??= null;
    $scopes = $livewire?->getRenderHookScopes();
@endphp
<x-filament-panels::layout.base :livewire="$livewire">
    <div class="yg-auth-layout">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $scopes) }}
        <aside class="yg-auth-story">
            @include('filament.shared.brand')
            <div class="yg-auth-story-copy">
                <p class="yg-eyebrow">{{ __('staff.team_workspace') }}</p>
                <h2>{{ __('staff.auth_story_title') }}</h2>
                <p>{{ __('staff.auth_story_description') }}</p>
            </div>
            <p class="yg-auth-story-footer">{{ __('staff.auth_story_footer') }}</p>
        </aside>
        <main id="fi-main-content" tabindex="-1" class="yg-auth-main">
            <div class="yg-auth-toolbar">@include('filament.staff.locale-switcher', ['panel' => filament()->getCurrentOrDefaultPanel()->getId()])</div>
            <section class="yg-auth-card">{{ $slot }}</section>
        </main>
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $scopes) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $scopes) }}
    </div>
</x-filament-panels::layout.base>
