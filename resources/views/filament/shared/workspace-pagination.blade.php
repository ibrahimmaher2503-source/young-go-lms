@if ($hasPagination && $records instanceof \Illuminate\Pagination\LengthAwarePaginator)
    @php
        $rtl = app()->getLocale() === 'ar';
        $pageName = $records->getPageName();
        $key = $table->getLivewire()->getId().'.workspace-pagination.'.$pageName;
        $targets = \Filament\Tables\Table::LOADING_TARGETS;
    @endphp
    <nav class="fi-pagination yg-workspace-pagination" aria-label="{{ __('components.pagination') }}" data-workspace-current-page="{{ $records->currentPage() }}">
        <span class="fi-pagination-overview">{{ __('staff.pagination_summary', ['first' => $records->firstItem(), 'last' => $records->lastItem(), 'total' => $records->total()]) }}</span>
        <ol class="fi-pagination-items">
            <x-filament::pagination.item
                :aria-label="__('components.previous')"
                :icon="$rtl ? 'heroicon-m-chevron-right' : 'heroicon-m-chevron-left'"
                :disabled="$records->onFirstPage()"
                :data-workspace-page-boundary="$records->onFirstPage() ? 'disabled' : 'enabled'"
                :loading-targets="$targets"
                :wire:click="'previousPage(\''.$pageName.'\')'"
                :wire:key="$key.'.previous'"
            />
            @foreach ($records->render()->offsetGet('elements') as $element)
                @if (is_string($element))
                    <x-filament::pagination.item disabled :label="$element" />
                @else
                    @foreach ($element as $page => $url)
                        <x-filament::pagination.item
                            :active="$page === $records->currentPage()"
                            :aria-label="trans_choice('filament::components/pagination.actions.go_to_page.label', $page, ['page' => $page])"
                            :label="$page"
                            :loading-targets="$targets"
                            :wire:click="'gotoPage('.$page.', \''.$pageName.'\')'"
                            :wire:key="$key.'.'.$page"
                        />
                    @endforeach
                @endif
            @endforeach
            <x-filament::pagination.item
                :aria-label="__('components.next')"
                :icon="$rtl ? 'heroicon-m-chevron-left' : 'heroicon-m-chevron-right'"
                :disabled="! $records->hasMorePages()"
                :data-workspace-page-boundary="$records->hasMorePages() ? 'enabled' : 'disabled'"
                :loading-targets="$targets"
                :wire:click="'nextPage(\''.$pageName.'\')'"
                :wire:key="$key.'.next'"
            />
        </ol>
    </nav>
@endif
