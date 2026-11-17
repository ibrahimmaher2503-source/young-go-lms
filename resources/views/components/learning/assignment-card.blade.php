{{-- Props: course, item, view (SubmissionView::for), viewer (acting learner, for the due time zone).
     States: empty (nothing saved yet), draft, submitted/resubmitted (locked, read only), returned (edit again), error and success flashes, loading on submit.
     Files are opened through the owner-checked download route; no storage path reaches this page. --}}
@props(['course', 'item', 'view', 'viewer' => null])
@php
    $assignment = $view['assignment'];
    $status = $view['status'];
    $zone = $viewer?->getAttribute('timezone');
    $zone = is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : config('app.timezone');
    $due = $view['due_at']?->copy()->timezone($zone)->isoFormat('LLL');
    $title = (string) $assignment->title;
    $tone = ['not_started' => 'neutral', 'draft' => 'info', 'submitted' => 'success', 'resubmitted' => 'success', 'returned' => 'warning', 'graded' => 'success'][$status] ?? 'neutral';
    $working = $view['working'];
    $locked = ! $view['can_edit'];
    $shown = $locked ? (collect($view['revisions'])->last()) : $working;
    $formId = 'assignment-'.$item->id;
    $latePolicy = $assignment->late_policy;
@endphp
<section class="space-y-5 rounded-card border border-border bg-surface p-5 shadow-card sm:p-8" aria-label="{{ $title }}" data-assignment-card data-assignment-status="{{ $status }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            <span class="grid size-12 place-items-center rounded-full bg-nav-active-bg text-primary" aria-hidden="true"><x-ui.icon name="file" class="size-6" /></span>
            <h3 class="break-words text-xl font-bold">{{ $title }}</h3>
        </div>
        <x-ui.badge :tone="$tone" data-assignment-badge>{{ __('assignment.statuses.'.$status) }}</x-ui.badge>
    </div>

    @if(filled($assignment->description))<p class="text-muted">{{ $assignment->description }}</p>@endif
    @if(filled($assignment->instructions))<div class="whitespace-pre-line break-words rounded-lg bg-page p-4" data-assignment-instructions>{{ $assignment->instructions }}</div>@endif

    @if($assignment->attachments->isNotEmpty())
        <div class="space-y-2" data-assignment-attachments>
            <h4 class="font-bold">{{ __('assignment.brief_files') }}</h4>
            <ul class="space-y-2">
                @foreach($assignment->attachments as $attachment)
                    <li><x-ui.link :href="route('assignment.attachment', ['attachment' => $attachment->id])" class="w-full justify-between gap-2 border border-border px-3 no-underline hover:bg-page"><span class="break-words">{{ __('learn.open_file', ['name' => (string) $attachment->title]) }}</span><x-ui.icon name="download" /></x-ui.link></li>
                @endforeach
            </ul>
        </div>
    @endif

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-sm text-muted">{{ __('assignment.due') }}</dt><dd class="text-lg font-bold" data-assignment-due>{{ $due ?? __('assignment.no_due_date') }}</dd></div>
        <div><dt class="text-sm text-muted">{{ __('assignment.accepts') }}</dt><dd class="text-lg font-bold">{{ collect(array_merge($view['rules']['text'] ? ['text'] : [], $view['rules']['kinds']))->map(fn ($k) => __('assignment.types.'.$k))->implode(__('assignment.list_separator')) }}</dd></div>
        <div><dt class="text-sm text-muted">{{ __('assignment.late_policy') }}</dt><dd class="text-lg font-bold">{{ __('assignment.late_policies.'.$latePolicy, ['percent' => $assignment->late_penalty_percent]) }}</dd></div>
        <div><dt class="text-sm text-muted">{{ __('assignment.revisions_left') }}</dt><dd class="text-lg font-bold" data-assignment-revisions-left>{{ $view['revisions_left'] }}</dd></div>
    </dl>

    @if(session('assignment_notice'))<x-ui.alert tone="success" role="status" data-assignment-notice>{{ session('assignment_notice') }}</x-ui.alert>@endif
    @if(session('assignment_error'))<x-ui.alert tone="danger" role="alert" data-assignment-error>{{ session('assignment_error') }}</x-ui.alert>@endif

    @if($view['is_past_due'])
        <x-ui.alert tone="warning" role="status" data-assignment-past-due>{{ $view['late_blocked'] ? __('assignment.past_due_blocked') : __('assignment.past_due_late') }}</x-ui.alert>
    @endif
    @if($status === 'returned')<x-ui.alert tone="warning" role="status" data-assignment-returned>{{ $view['can_edit'] ? __('assignment.returned_note') : __('assignment.refused.revisions_exhausted') }}</x-ui.alert>@endif

    @if($locked)
        {{-- Handed in: read only. --}}
        @if($shown === null)
            <x-ui.empty-state :title="__('assignment.empty_title')" :description="__('assignment.empty_description')" data-assignment-empty />
        @else
            <div class="space-y-3 rounded-lg border border-border p-4" data-assignment-locked>
                <p class="font-semibold">{{ __('assignment.handed_in', ['number' => $shown['number']]) }}@if($shown['submitted_at']) · <time datetime="{{ $shown['submitted_at']->toIso8601String() }}">{{ $shown['submitted_at']->copy()->timezone($zone)->isoFormat('LLL') }}</time>@endif</p>
                @if($shown['is_late'])<x-ui.badge tone="warning" data-assignment-late>{{ $shown['late_penalty_percent'] ? __('assignment.late_penalty', ['percent' => $shown['late_penalty_percent']]) : __('assignment.late') }}</x-ui.badge>@endif
                @if(filled($shown['text']))<div class="whitespace-pre-line break-words rounded-lg bg-page p-3" dir="auto">{{ $shown['text'] }}</div>@endif
                <x-ui.submission-uploader :id="$formId.'-locked'" :files="$shown['files']" disabled />
            </div>
        @endif
    @else
        @if($status === 'not_started')
            <x-ui.empty-state :title="__('assignment.empty_title')" :description="__('assignment.empty_description')" data-assignment-empty />
        @endif
        <form method="POST" action="{{ route('assignment.save', ['course' => $course->id, 'item' => $item->id]) }}" enctype="multipart/form-data" class="space-y-4" data-assignment-form>
            @csrf
            @if($view['rules']['text'])
                <div class="space-y-1">
                    <label for="{{ $formId }}-text" class="block font-semibold">{{ __('assignment.your_answer') }}</label>
                    <x-ui.textarea id="{{ $formId }}-text" name="text" rows="8" maxlength="{{ $view['rules']['max_text'] }}" aria-describedby="{{ $formId }}-text-hint" dir="auto">{{ old('text', $working['text'] ?? '') }}</x-ui.textarea>
                    <p id="{{ $formId }}-text-hint" class="text-sm text-muted">{{ __('assignment.text_hint', ['max' => number_format($view['rules']['max_text'])]) }}</p>
                </div>
            @endif
            @if($view['rules']['kinds'] !== [])
                <x-ui.submission-uploader :id="$formId.'-files'" :accept="$view['rules']['accept']" :files="$working['files'] ?? []" :max-files="$view['rules']['max_files']" :limit-kb="$view['rules']['limit_kb']" />
            @endif
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-border bg-surface px-5 font-bold" data-assignment-save>{{ __('assignment.save_draft') }}</button>
                @if($view['can_submit'])
                    <button type="submit" formaction="{{ route('assignment.submit', ['course' => $course->id, 'item' => $item->id]) }}" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary" data-assignment-submit>{{ $status === 'returned' ? __('assignment.resubmit') : __('assignment.submit') }}</button>
                @endif
                <span class="hidden" role="status" data-assignment-loading><x-ui.skeleton :label="__('assignment.uploading')" /></span>
            </div>
        </form>
    @endif

    @if(count($view['revisions']) > 1)
        <div class="space-y-2 border-t border-border pt-4">
            <h4 class="font-bold">{{ __('assignment.history') }}</h4>
            <ul class="space-y-2" data-assignment-history>
                @foreach($view['revisions'] as $revision)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border px-3 py-2">
                        <span class="font-semibold">{{ __('assignment.revision_no', ['number' => $revision['number']]) }}</span>
                        <span class="text-sm text-muted">@if($revision['submitted_at'])<time datetime="{{ $revision['submitted_at']->toIso8601String() }}">{{ $revision['submitted_at']->copy()->timezone($zone)->isoFormat('LLL') }}</time>@endif @if($revision['is_late']) · {{ __('assignment.late') }}@endif</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
