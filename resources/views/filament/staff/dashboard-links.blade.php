{{-- D1: capped, grouped shortcuts. Rows are prepared by WorkspaceDashboard::quickAccess (queue targets and duplicates removed). --}}
<section class="yg-dashboard-tools yg-quick-access-card" data-quick-access>
    <header class="yg-overview-card-header is-compact">
        <div class="yg-overview-title-group">
            <span class="yg-overview-badge-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-squares-2x2" />
            </span>
            <div>
                <h2>{{ __('staff.quick_access') }}</h2>
                <p>{{ __('staff.quick_access_description') }}</p>
            </div>
        </div>
    </header>
    @if($groups)
        <div class="yg-quick-groups">
            @foreach($groups as $group)
                <div class="yg-dashboard-group" data-quick-group>
                    @if(filled($group['label']) && ! (count($group['links']) === 1 && $group['links'][0]['label'] === $group['label']))<h3 class="yg-dashboard-group-title">{{ $group['label'] }}</h3>@endif
                    <div class="yg-dashboard-links">
                        @foreach($group['links'] as $link)
                            <a href="{{ $link['url'] }}" class="yg-dashboard-link">
                                <span class="yg-quick-link-icon" aria-hidden="true"><x-filament::icon :icon="$link['icon']" /></span>
                                <span>{{ $link['label'] }}</span>
                                <x-filament::icon icon="heroicon-o-chevron-left" class="yg-directional-icon" aria-hidden="true" />
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="yg-dashboard-empty"><x-filament::icon icon="heroicon-o-window" aria-hidden="true" /><h3>{{ __('staff.workspace_empty_title') }}</h3><p>{{ __('staff.workspace_empty_description') }}</p></div>
    @endif
</section>
