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
        .yg-cur-sechead { background: var(--yg-color-surface-muted, var(--yg-color-page)); border-block-end: 1px solid var(--yg-color-border); flex-wrap: nowrap; }
        .yg-cur-secinfo { flex: 1 1 auto; min-inline-size: 0; }
        .yg-cur-chips { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .yg-cur-withicon { display: inline-flex; align-items: center; gap: .25rem; }
        .yg-cur-withicon svg, .yg-cur-iconchip svg, .yg-cur-iconbtn svg { inline-size: 1.125rem; block-size: 1.125rem; }
        .yg-cur-iconchip { display: inline-grid; place-items: center; inline-size: 2rem; block-size: 2rem; border-radius: .5rem; background: var(--yg-color-info); color: var(--yg-color-on-info); }
        .yg-cur-sr { position: absolute; inline-size: 1px; block-size: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
        .yg-cur-status { display: inline-flex; align-items: center; gap: .375rem; padding: .125rem .75rem; border-radius: 999px; font-size: .8125rem; font-weight: 600; background: var(--yg-color-page); color: var(--yg-color-body); }
        .yg-cur-status i { inline-size: .5rem; block-size: .5rem; border-radius: 50%; background: var(--yg-color-muted); }
        .yg-cur-status.is-published { background: var(--yg-color-success); color: var(--yg-color-on-success); }
        .yg-cur-status.is-published i { background: var(--yg-color-on-success); }
        .yg-cur-menu { position: relative; }
        .yg-cur-menu > summary { list-style: none; cursor: pointer; }
        .yg-cur-menu > summary::-webkit-details-marker { display: none; }
        .yg-cur-iconbtn { display: inline-grid; place-items: center; min-inline-size: 2.75rem; min-block-size: 2.75rem; border-radius: .5rem; color: var(--yg-color-body); }
        .yg-cur-iconbtn:hover { background: var(--yg-color-hover); }
        .yg-cur-menulist { position: absolute; inset-inline-end: 0; z-index: 20; min-inline-size: 11rem; display: grid; padding: .25rem; background: var(--yg-color-surface); border: 1px solid var(--yg-color-border); border-radius: .75rem; box-shadow: var(--yg-shadow-floating); }
        .yg-cur-menuitem { min-block-size: 2.75rem; padding: 0 .75rem; text-align: start; border-radius: .5rem; background: transparent; color: var(--yg-color-body); font-weight: 600; }
        .yg-cur-menuitem:hover:not(:disabled) { background: var(--yg-color-hover); }
        .yg-cur-menuitem:disabled { opacity: .5; cursor: not-allowed; }
        .yg-cur-menuitem.is-danger { color: var(--yg-color-on-danger); }
        .yg-cur-item[data-selected], .yg-cur-item:focus-within { background: var(--yg-color-nav-active-bg, var(--yg-color-accent)); }
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
                    <div class="yg-cur-head yg-cur-sechead">
                        <span class="yg-cur-handle" aria-hidden="true" title="{{ __('curriculum.drag') }}">⋮⋮</span>
                        <div class="yg-cur-secinfo">
                            <h3 id="section-title-{{ $section->id }}" class="yg-cur-title">{{ str_pad((string) ($s + 1), 2, '0', STR_PAD_LEFT) }}. {{ $section->title }}</h3>
                            <span class="yg-cur-meta">{{ __('curriculum.lessons_count', ['count' => $section->items->count()]) }} · {{ __('curriculum.minutes', ['count' => $minutes($section)]) }}</span>
                        </div>
                        <span class="yg-cur-status {{ $section->published ? 'is-published' : '' }}"><i></i>{{ $section->published ? __('curriculum.status_published') : __('curriculum.status_draft') }}</span>
                        <details class="yg-cur-menu">
                            <summary class="yg-cur-iconbtn" aria-label="{{ __('curriculum.more') }}: {{ $section->title }}"><x-ui.icon name="more" /></summary>
                            <div class="yg-cur-menulist">
                                <button type="button" class="yg-cur-menuitem" wire:click="moveSection({{ $section->id }}, 'up')" @disabled($s === 0)>{{ __('curriculum.move_up') }}</button>
                                <button type="button" class="yg-cur-menuitem" wire:click="moveSection({{ $section->id }}, 'down')" @disabled($s === $sections->count() - 1)>{{ __('curriculum.move_down') }}</button>
                                <button type="button" class="yg-cur-menuitem" wire:click="setSectionPublished({{ $section->id }}, {{ $section->published ? 'false' : 'true' }})">{{ $section->published ? __('curriculum.unpublish_section') : __('curriculum.publish_section') }}</button>
                                <button type="button" class="yg-cur-menuitem is-danger" wire:click="mountAction('archiveSection', { section: {{ $section->id }} })">{{ __('curriculum.archive_section') }}</button>
                            </div>
                        </details>
                    </div>
                    <ul class="yg-cur-items" data-items x-on:dragover="over($event)" x-on:drop="drop($event)">
                        @forelse($section->items as $i => $item)
                            @php($lesson = $item->itemable)
                            @php($isLesson = $item->itemable_type === 'lesson')
                            @php($document = $isLesson ? $lesson->curriculumDocuments->first() : null)
                            @php($minutesCount = $isLesson && $lesson->duration_seconds ? (int) round($lesson->duration_seconds / 60) : null)
                            <li class="yg-cur-item" data-item="{{ $item->id }}" wire:key="item-{{ $item->id }}" draggable="true" x-on:dragstart="start($event)" x-on:dragend="end()" @if(in_array($item->id, $selected)) data-selected @endif>
                                <input type="checkbox" class="yg-cur-check" value="{{ $item->id }}" wire:model.live="selected" aria-label="{{ __('curriculum.select') }}: {{ $lesson->title }}">
                                <span class="yg-cur-handle" aria-hidden="true" title="{{ __('curriculum.drag') }}">⋮⋮</span>
                                <span class="yg-cur-num">{{ $i + 1 }}</span>
                                <span class="yg-cur-name">{{ $lesson->title }}</span>
                                <span class="yg-cur-chips">
                                    @if($minutesCount)<span class="yg-cur-meta yg-cur-withicon"><x-ui.icon name="clock" /> {{ __('curriculum.minutes', ['count' => $minutesCount]) }}</span>@endif
                                    <span class="yg-cur-iconchip" title="{{ $isLesson ? __("curriculum.types.{$lesson->type}") : __("curriculum.types.{$item->itemable_type}") }}"><x-ui.icon :name="$isLesson ? ($lesson->type === 'video' ? 'video' : 'file') : 'clipboard'" /><span class="yg-cur-sr">{{ $isLesson ? __("curriculum.types.{$lesson->type}") : __("curriculum.types.{$item->itemable_type}") }}</span></span>
                                    @if($isLesson && $lesson->media)
                                        <span class="yg-cur-badge" title="{{ __('media.tier') }}">{{ __('media.tier') }}: {{ __('media.tiers.'.$lesson->media->protection_tier) }}@if($lesson->media->hasException()) · {{ __('media.exception') }}@endif</span>
                                    @endif
                                    @if($document)
                                        <span class="yg-cur-badge" title="{{ __('curriculum.curriculum_pdf') }}">{{ __('curriculum.pdf_version', ['version' => $document->version]) }} · {{ __('curriculum.pdf_status.'.$document->status) }}</span>
                                    @endif
                                    @if($isLesson && $lesson->resources->count() > 0)
                                        <span class="yg-cur-meta">{{ __('curriculum.resources_count', ['count' => $lesson->resources->count()]) }}</span>
                                    @endif
                                    @if($isLesson && $lesson->media && $lesson->media->status !== 'ready')
                                        <span class="yg-cur-badge">{{ __('media.status.'.$lesson->media->status) }}</span>
                                    @endif
                                    @if($item->itemable_type === 'live_session')
                                        <span class="yg-cur-meta yg-cur-withicon" data-live-time><x-ui.icon name="clock" /> {{ \App\Domain\Live\LiveSessionTime::label($lesson, auth()->user()) }}</span>
                                        <span class="yg-cur-badge" data-live-status="{{ $lesson->status }}">{{ __('live.status.'.$lesson->status) }}</span>
                                    @endif
                                    {{-- Assistant-readiness indicator (T-P3-09) --}}
                                    @if($isLesson) @includeIf('filament.courses.curriculum-readiness', ['item' => $item]) @endif
                                </span>
                                <span class="yg-cur-status {{ $item->status === 'published' ? 'is-published' : '' }}"><i></i>{{ $item->status === 'published' ? __('curriculum.status_published') : __('curriculum.status_draft') }}</span>
                                <details class="yg-cur-menu">
                                    <summary class="yg-cur-iconbtn" aria-label="{{ __('curriculum.more') }}: {{ $lesson->title }}"><x-ui.icon name="more" /></summary>
                                    <div class="yg-cur-menulist">
                                        <button type="button" class="yg-cur-menuitem" wire:click="moveItem({{ $item->id }}, 'up')">{{ __('curriculum.move_up') }}</button>
                                        <button type="button" class="yg-cur-menuitem" wire:click="moveItem({{ $item->id }}, 'down')">{{ __('curriculum.move_down') }}</button>
                                        @if($isLesson)<button type="button" class="yg-cur-menuitem" wire:click="mountAction('editLesson', { lesson: {{ $item->itemable_id }} })">{{ __('curriculum.edit') }}</button>@endif
                                        @if($item->itemable_type === 'live_session')
                                            @if($lesson->status === 'scheduled')
                                                <button type="button" class="yg-cur-menuitem" wire:click="mountAction('rescheduleLiveSession', { session: {{ $lesson->id }} })">{{ __('live.reschedule') }}</button>
                                                <button type="button" class="yg-cur-menuitem" wire:click="advanceLiveSession({{ $lesson->id }}, 'open')">{{ __('live.open') }}</button>
                                            @endif
                                            @if(in_array($lesson->status, ['open', 'completed'], true))<button type="button" class="yg-cur-menuitem" wire:click="mountAction('attendance', { session: {{ $lesson->id }} })">{{ __('live.attendance.mark') }}</button>@endif
                                            @if($lesson->status === 'completed' && $lesson->replay_item_id === null)<button type="button" class="yg-cur-menuitem" wire:click="publishLiveReplay({{ $lesson->id }})">{{ __('live.replay.publish') }}</button>@endif
                                            @if($lesson->status === 'open')<button type="button" class="yg-cur-menuitem" wire:click="advanceLiveSession({{ $lesson->id }}, 'complete')">{{ __('live.complete') }}</button>@endif
                                            @if(in_array($lesson->status, ['scheduled', 'open'], true))<button type="button" class="yg-cur-menuitem is-danger" wire:click="mountAction('cancelLiveSession', { session: {{ $lesson->id }} })">{{ __('live.cancel') }}</button>@endif
                                        @endif
                                        <button type="button" class="yg-cur-menuitem is-danger" wire:click="{{ $isLesson ? "mountAction('archiveLesson', { lesson: {$item->itemable_id} })" : "mountAction('archiveAssessment', { item: {$item->id} })" }}">{{ __('curriculum.archive') }}</button>
                                    </div>
                                </details>
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
