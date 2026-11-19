{{-- D1: capped, grouped shortcuts. Rows are prepared by WorkspaceDashboard::quickAccess (queue targets and duplicates removed). --}}
<section class="yg-dashboard-tools" data-quick-access>
    <header><h2>{{ __('staff.quick_access') }}</h2><p>{{ __('staff.quick_access_description') }}</p></header>
    @if($groups)
        @foreach($groups as $group)
            <div class="yg-dashboard-group" data-quick-group>
                @if(filled($group['label']) && ! (count($group['links']) === 1 && $group['links'][0]['label'] === $group['label']))<h3 class="yg-dashboard-group-title">{{ $group['label'] }}</h3>@endif
                <div class="yg-dashboard-links">
                    @foreach($group['links'] as $link)
                        <a href="{{ $link['url'] }}" class="yg-dashboard-link"><x-filament::icon :icon="$link['icon']" aria-hidden="true" /><span>{{ $link['label'] }}</span><x-filament::icon icon="heroicon-o-chevron-left" class="yg-directional-icon" aria-hidden="true" /></a>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="yg-dashboard-empty"><x-filament::icon icon="heroicon-o-window" aria-hidden="true" /><h3>{{ __('staff.workspace_empty_title') }}</h3><p>{{ __('staff.workspace_empty_description') }}</p></div>
    @endif
</section>
