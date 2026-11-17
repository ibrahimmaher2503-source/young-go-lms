<x-filament-panels::page>
    {{-- The Filament theme does not scan this folder, so the rules live here and use the shared tokens. --}}
    <style>
        .yg-cur { display: grid; gap: 1rem; }
        .yg-cur-card { background: var(--yg-color-surface); border: 1px solid var(--yg-color-border); border-radius: var(--yg-radius-card); box-shadow: var(--yg-shadow-card); }
        .yg-cur-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .875rem 1rem; border-block-end: 1px solid var(--yg-color-border); }
        .yg-cur-title { font-weight: 700; margin: 0; overflow-wrap: anywhere; }
        .yg-cur-meta { color: var(--yg-color-muted); font-size: .875rem; }
        .yg-cur-items { list-style: none; margin: 0; padding: 0; min-block-size: 2.5rem; }
        .yg-cur-item { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; padding: .625rem 1rem; border-block-end: 1px solid var(--yg-color-border); }
        .yg-cur-item:last-child { border-block-end: 0; }
        .yg-cur-item[data-selected] { background: var(--yg-color-accent); }
        .yg-cur-handle { cursor: grab; color: var(--yg-color-muted); user-select: none; letter-spacing: -.15em; }
        .yg-cur-num { display: inline-grid; place-items: center; min-inline-size: 1.75rem; block-size: 1.75rem; border-radius: .5rem; background: var(--yg-color-page); font-weight: 600; }
        .yg-cur-name { flex: 1 1 10rem; min-inline-size: 0; overflow-wrap: anywhere; font-weight: 600; }
        .yg-cur-badge { display: inline-flex; align-items: center; padding: .125rem .625rem; border-radius: 999px; font-size: .8125rem; font-weight: 600; background: var(--yg-color-page); }
        .yg-cur-badge.is-published { background: var(--yg-color-success); color: var(--yg-color-on-success); }
        .yg-cur-actions { display: flex; flex-wrap: wrap; gap: .25rem; }
        .yg-cur-btn { min-inline-size: 2.75rem; min-block-size: 2.75rem; padding: 0 .75rem; border: 1px solid var(--yg-color-border); border-radius: .5rem; background: var(--yg-color-surface); color: var(--yg-color-body); cursor: pointer; font: inherit; }
        .yg-cur-btn:hover { background: var(--yg-color-page); }
        .yg-cur-btn.is-primary { background: var(--yg-color-primary); color: var(--yg-color-on-primary); border-color: transparent; }
        .yg-cur-btn.is-danger { background: var(--yg-color-danger); color: var(--yg-color-on-danger); border-color: transparent; }
        .yg-cur-bulk { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .75rem 1rem; }
        .yg-cur-empty { padding: 1.5rem 1rem; text-align: center; color: var(--yg-color-muted); }
        .yg-cur-note { padding: .75rem 1rem; color: var(--yg-color-muted); }
        .yg-cur-check { inline-size: 1.25rem; block-size: 1.25rem; accent-color: var(--yg-color-primary); }
        .yg-cur-hidden { position: absolute; inline-size: 1px; block-size: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
        [data-dragging] { opacity: .5; }
    </style>

    @php($minutes = static fn ($section): int => (int) round($section->items->sum(static fn ($item): int => (int) ($item->itemable->duration_seconds ?? 0)) / 60))

    <div class="yg-cur"
        x-data="{
            dragged: null,
            start(event) { this.dragged = event.target.closest('[data-item]'); if (!this.dragged) return; this.dragged.setAttribute('data-dragging', ''); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', this.dragged.dataset.item); },
            over(event) {
                if (!this.dragged) return;
                const list = event.target.closest('[data-items]');
                if (!list) return;
                event.preventDefault();
                const target = event.target.closest('[data-item]');
                if (target && target !== this.dragged) {
                    const box = target.getBoundingClientRect();
                    list.insertBefore(this.dragged, event.clientY - box.top > box.height / 2 ? target.nextSibling : target);
                } else if (!target) {
                    list.appendChild(this.dragged);
                }
            },
            drop(event) { if (!this.dragged) return; event.preventDefault(); this.save(); },
            end() { if (this.dragged) this.dragged.removeAttribute('data-dragging'); this.dragged = null; },
            save() {
                const layout = [...$root.querySelectorAll('[data-section]')].map((section) => ({ section_id: Number(section.dataset.section), items: [...section.querySelectorAll('[data-item]')].map((item) => Number(item.dataset.item)) }));
                $wire.reorder(layout);
            },
        }">

        @if($previewing)
            <section class="yg-cur-card" aria-labelledby="yg-preview-title">
                <div class="yg-cur-head"><h2 id="yg-preview-title" class="yg-cur-title">{{ __('curriculum.student_preview') }}</h2></div>
                <p class="yg-cur-note">{{ __('curriculum.preview_note') }}</p>
                @forelse($student as $section)
                    <div class="yg-cur-head"><h3 class="yg-cur-title">{{ $section->title }}</h3></div>
                    <ol class="yg-cur-items">
                        @foreach($section->items as $i => $item)
                            <li class="yg-cur-item"><span class="yg-cur-num">{{ $i + 1 }}</span><span class="yg-cur-name">{{ $item->itemable->title }}</span></li>
                        @endforeach
                    </ol>
                @empty
                    <p class="yg-cur-empty">{{ __('curriculum.preview_empty') }}</p>
                @endforelse
            </section>
        @endif

        @if(count($selected) > 0)
            <div class="yg-cur-card yg-cur-bulk" role="region" aria-label="{{ __('curriculum.selected', ['count' => count($selected)]) }}">
                <strong>{{ __('curriculum.selected', ['count' => count($selected)]) }}</strong>
                <button type="button" class="yg-cur-btn is-primary" wire:click="bulkStatus('published')">{{ __('curriculum.publish_selected') }}</button>
                <button type="button" class="yg-cur-btn" wire:click="bulkStatus('draft')">{{ __('curriculum.unpublish_selected') }}</button>
            </div>
        @endif

        <div class="yg-cur-card">
            <div class="yg-cur-head"><h2 class="yg-cur-title">{{ __('curriculum.content') }}</h2></div>
            @if($sections->isEmpty())
                <div class="yg-cur-empty">
                    <p><strong>{{ __('curriculum.empty_title') }}</strong></p>
                    <p>{{ __('curriculum.empty_text') }}</p>
                </div>
            @endif

            @foreach($sections as $s => $section)
                <section class="yg-cur-sectionbox" data-section="{{ $section->id }}" wire:key="section-{{ $section->id }}" aria-labelledby="section-title-{{ $section->id }}" style="margin: 1rem; border: 1px solid var(--yg-color-border); border-radius: var(--yg-radius-card);">
                    <div class="yg-cur-head" style="background: var(--yg-color-page);">
                        <div>
                            <h3 id="section-title-{{ $section->id }}" class="yg-cur-title">{{ $s + 1 }}. {{ $section->title }}</h3>
                            <span class="yg-cur-meta">{{ __('curriculum.lessons_count', ['count' => $section->items->count()]) }} · {{ __('curriculum.minutes', ['count' => $minutes($section)]) }}</span>
                        </div>
                        <div class="yg-cur-actions">
                            <span class="yg-cur-badge {{ $section->published ? 'is-published' : '' }}">{{ $section->published ? __('curriculum.status_published') : __('curriculum.status_draft') }}</span>
                            <button type="button" class="yg-cur-btn" wire:click="moveSection({{ $section->id }}, 'up')" @disabled($s === 0) aria-label="{{ __('curriculum.move_up') }}: {{ $section->title }}">↑</button>
                            <button type="button" class="yg-cur-btn" wire:click="moveSection({{ $section->id }}, 'down')" @disabled($s === $sections->count() - 1) aria-label="{{ __('curriculum.move_down') }}: {{ $section->title }}">↓</button>
                            <button type="button" class="yg-cur-btn" wire:click="setSectionPublished({{ $section->id }}, {{ $section->published ? 'false' : 'true' }})">{{ $section->published ? __('curriculum.unpublish_section') : __('curriculum.publish_section') }}</button>
                            <button type="button" class="yg-cur-btn is-danger" wire:click="mountAction('archiveSection', { section: {{ $section->id }} })">{{ __('curriculum.archive_section') }}</button>
                        </div>
                    </div>
                    <ul class="yg-cur-items" data-items x-on:dragover="over($event)" x-on:drop="drop($event)">
                        @forelse($section->items as $i => $item)
                            @php($lesson = $item->itemable)
                            <li class="yg-cur-item" data-item="{{ $item->id }}" wire:key="item-{{ $item->id }}" draggable="true" x-on:dragstart="start($event)" x-on:dragend="end()" @if(in_array($item->id, $selected)) data-selected @endif>
                                <input type="checkbox" class="yg-cur-check" value="{{ $item->id }}" wire:model.live="selected" aria-label="{{ __('curriculum.select') }}: {{ $lesson->title }}">
                                <span class="yg-cur-handle" aria-hidden="true" title="{{ __('curriculum.drag') }}">⋮⋮</span>
                                <span class="yg-cur-num">{{ $i + 1 }}</span>
                                <span class="yg-cur-name">{{ $lesson->title }}</span>
                                <span class="yg-cur-meta">{{ __("curriculum.types.{$lesson->type}") }}@if($lesson->duration_seconds) · {{ __('curriculum.minutes', ['count' => (int) round($lesson->duration_seconds / 60)]) }}@endif</span>
                                @if($lesson->media)
                                    <span class="yg-cur-badge" title="{{ __('media.tier') }}">{{ __('media.tier') }}: {{ __('media.tiers.'.$lesson->media->protection_tier) }}@if($lesson->media->hasException()) · {{ __('media.exception') }}@endif</span>
                                @endif
                                @if($lesson->media && $lesson->media->status !== 'ready')
                                    <span class="yg-cur-badge">{{ __('media.status.'.$lesson->media->status) }}</span>
                                @endif
                                <span class="yg-cur-badge {{ $item->status === 'published' ? 'is-published' : '' }}">{{ $item->status === 'published' ? __('curriculum.status_published') : __('curriculum.status_draft') }}</span>
                                {{-- Slot for the assistant-readiness indicator (T-P3-09): created later, ignored until the view exists. --}}
                                @includeIf('filament.courses.curriculum-readiness', ['item' => $item])
                                <span class="yg-cur-actions">
                                    <button type="button" class="yg-cur-btn" wire:click="moveItem({{ $item->id }}, 'up')" aria-label="{{ __('curriculum.move_up') }}: {{ $lesson->title }}">↑</button>
                                    <button type="button" class="yg-cur-btn" wire:click="moveItem({{ $item->id }}, 'down')" aria-label="{{ __('curriculum.move_down') }}: {{ $lesson->title }}">↓</button>
                                    <button type="button" class="yg-cur-btn" wire:click="mountAction('editLesson', { lesson: {{ $item->itemable_id }} })">{{ __('curriculum.edit') }}</button>
                                    <button type="button" class="yg-cur-btn is-danger" wire:click="mountAction('archiveLesson', { lesson: {{ $item->itemable_id }} })">{{ __('curriculum.archive') }}</button>
                                </span>
                            </li>
                        @empty
                            <li class="yg-cur-empty">{{ __('curriculum.empty_section') }}</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>

        <details class="yg-cur-card">
            <summary class="yg-cur-head"><span class="yg-cur-title">{{ __('curriculum.archived') }} ({{ $archived->count() }})</span></summary>
            @forelse($archived as $item)
                <p class="yg-cur-note">{{ $item->itemable->title }}</p>
            @empty
                <p class="yg-cur-note">{{ __('curriculum.no_archived') }}</p>
            @endforelse
        </details>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
