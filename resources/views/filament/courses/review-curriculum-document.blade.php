<x-filament-panels::page>
    {{-- The Filament theme does not scan this folder, so the rules live here and use the shared tokens. --}}
    <style>
        .yg-rev { display: grid; gap: 1rem; }
        .yg-rev-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .yg-rev-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 64rem) { .yg-rev-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
        .yg-rev-card { background: var(--yg-color-surface); border: 1px solid var(--yg-color-border); border-radius: var(--yg-radius-card); box-shadow: var(--yg-shadow-card); padding: 1rem; display: grid; gap: .75rem; align-content: start; }
        .yg-rev-title { font-weight: 700; margin: 0; }
        .yg-rev-meta { color: var(--yg-color-muted); font-size: .875rem; }
        .yg-rev-frame { inline-size: 100%; block-size: 70vh; min-block-size: 24rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; background: var(--yg-color-page); }
        .yg-rev-text { inline-size: 100%; min-block-size: 9rem; padding: .625rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; background: var(--yg-color-surface); color: var(--yg-color-text); font: inherit; line-height: 1.7; }
        .yg-rev-badge { display: inline-flex; align-items: center; padding: .125rem .625rem; border-radius: 999px; font-size: .8125rem; font-weight: 600; background: var(--yg-color-page); }
        .yg-rev-btn { min-block-size: 2.75rem; padding: 0 1rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; background: var(--yg-color-surface); color: var(--yg-color-text); font-weight: 600; cursor: pointer; }
        .yg-rev-btn.is-primary { background: var(--yg-color-primary); color: var(--yg-color-on-primary); border-color: transparent; }
        .yg-rev-fail { padding: .75rem 1rem; border-radius: .5rem; background: var(--yg-color-danger); color: var(--yg-color-on-danger); font-weight: 600; }
        .yg-rev-terms { display: flex; flex-wrap: wrap; gap: .5rem 1rem; }
        .yg-rev-terms label { display: inline-flex; align-items: center; gap: .5rem; min-block-size: 2.75rem; }
    </style>

    <div class="yg-rev">
        <div class="yg-rev-bar">
            <span class="yg-rev-badge" data-readiness="{{ $readiness->value }}"><span aria-hidden="true">{{ $readiness->icon() }}</span>&nbsp;{{ $readiness->label() }}</span>
            <span class="yg-rev-meta">{{ __('curriculum.pdf_version', ['version' => $document->version]) }} · {{ __('curriculum.pdf_status.'.$document->status) }}@if($document->quality_score !== null) · {{ __('media.quality', ['score' => number_format((float) $document->quality_score, 0)]) }}@endif</span>
            @if($canApprove)
                <button type="button" class="yg-rev-btn is-primary" wire:click="approve" wire:loading.attr="disabled">{{ __('media.approve') }}</button>
            @endif
        </div>
        <p class="yg-rev-meta">{{ __('media.review_help') }}</p>

        @if($failure !== '')
            <div class="yg-rev-fail" role="alert">{{ $failure }}</div>
        @endif

        <div class="yg-rev-grid">
            <section class="yg-rev-card" aria-labelledby="pdf-pane">
                <h2 id="pdf-pane" class="yg-rev-title">{{ __('media.pdf_pane') }}</h2>
                @if($pdfUrl)
                    <iframe class="yg-rev-frame" src="{{ $pdfUrl }}" title="{{ __('media.pdf_pane') }}"></iframe>
                @else
                    <p class="yg-rev-meta">{{ __('media.pdf_unavailable') }}</p>
                @endif
            </section>

            <section class="yg-rev-card" aria-labelledby="text-pane">
                <h2 id="text-pane" class="yg-rev-title">{{ __('media.text_pane') }}</h2>
                @forelse($pages as $page)
                    <div wire:key="page-{{ $page->page_no }}" style="display:grid;gap:.5rem">
                        <div class="yg-rev-bar">
                            <label for="page-text-{{ $page->page_no }}" class="yg-rev-title">{{ __('media.page', ['page' => $page->page_no]) }}</label>
                            <span class="yg-rev-badge">{{ __('media.quality', ['score' => number_format((float) $page->quality_score, 0)]) }}</span>
                            @if($page->ocr_used)
                                <span class="yg-rev-badge">{{ __('media.ocr_flag') }}</span>
                            @endif
                        </div>
                        <textarea id="page-text-{{ $page->page_no }}" class="yg-rev-text" wire:model="texts.{{ $page->page_no }}" @disabled(! $canApprove) aria-label="{{ __('media.page_text', ['page' => $page->page_no]) }}">{{ $texts[$page->page_no] ?? '' }}</textarea>
                        @if($canApprove)
                            <div><button type="button" class="yg-rev-btn" wire:click="savePage({{ $page->page_no }})">{{ __('media.save_page') }}</button></div>
                        @endif
                    </div>
                @empty
                    <p class="yg-rev-meta">{{ __('media.no_pages') }}</p>
                @endforelse
            </section>
        </div>

        @if($terms_list->isNotEmpty())
            <section class="yg-rev-card" aria-labelledby="terms-title">
                <h2 id="terms-title" class="yg-rev-title">{{ __('media.key_terms') }}</h2>
                <p class="yg-rev-meta">{{ __('media.key_terms_help') }}</p>
                <div class="yg-rev-terms">
                    @foreach($terms_list as $term)
                        <label wire:key="term-{{ $term->id }}"><input type="checkbox" value="{{ $term->id }}" wire:model="terms"> {{ $term->term }}</label>
                    @endforeach
                </div>
                <div><button type="button" class="yg-rev-btn" wire:click="saveTerms">{{ __('media.save_terms') }}</button></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
