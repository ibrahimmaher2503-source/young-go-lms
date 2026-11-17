{{-- Props: lesson (recorded lesson the learner may use the assistant on), pdfUrl (signed lesson PDF url or null).
     Render only when the server already decided the learner is eligible: nothing about the assistant exists in the page otherwise.
     Desktop (2xl): column beside the player. Smaller screens: bottom sheet opened by <x-learning.assistant-opener>. Behaviour: resources/js/assistant.js.
     Feedback (T-P7-04): data-feedback-url is the lesson's feedback endpoint; "still not understood" creates a help request and the panel shows the request-created state. --}}
@props(['lesson', 'pdfUrl' => null])
@php
    $max = (int) config('ai.question_max_chars', 300);
    $strings = [
        'pausedAt' => __('ai.timestamp'),
        'characters' => __('ai.characters'),
        'sourcePage' => __('ai.source_page'),
        'openSourcePage' => __('ai.open_source_page'),
        'loading' => __('ai.loading'),
        'answered' => __('ai.answered'),
        'typing' => __('ai.panel.typing'),
        'networkError' => __('ai.panel.network_error'),
        'unavailable' => __('ai.temporarily_unavailable'),
        'quota' => __('ai.quota_reached'),
        'quotaReset' => __('ai.panel.quota_reset'),
        'notInSource' => __('ai.not_in_source'),
        'notInSourceHint' => __('ai.panel.not_in_source_hint'),
        'outOfScope' => __('ai.out_of_scope'),
        'outOfScopeHint' => __('ai.panel.out_of_scope_hint'),
        'emptyQuestion' => __('ai.panel.empty_question'),
        'questionInvalid' => __('ai.question_invalid', ['max' => $max]),
        'feedbackError' => __('help.panel.feedback_error'),
        'viewThread' => __('help.panel.view_thread'),
    ];
@endphp
<aside id="ai-panel" data-ai-panel data-ai-slot data-state="idle" data-open="false"
    data-status-url="{{ route('assistant.show', $lesson) }}" data-ask-url="{{ route('assistant.ask', $lesson) }}" data-feedback-url="{{ route('assistant.feedback', $lesson) }}" data-pdf-url="{{ $pdfUrl }}"
    data-strings="{{ json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
    aria-labelledby="ai-panel-title"
    class="fixed inset-x-0 bottom-0 z-40 hidden max-h-[80dvh] flex-col gap-3 overflow-hidden rounded-t-3xl border border-border bg-surface p-4 shadow-card data-[open=true]:flex 2xl:sticky 2xl:top-4 2xl:z-auto 2xl:flex 2xl:max-h-[calc(100dvh-2rem)] 2xl:rounded-card">
    <div class="flex items-center justify-between gap-2">
        <h2 id="ai-panel-title" class="flex items-center gap-2 text-xl font-bold"><x-ui.icon name="sparkle" class="size-6 text-primary" /> <span>{{ __('ai.title') }}</span></h2>
        <x-ui.button type="button" variant="ghost" size="sm" data-ai-close class="2xl:hidden" :aria-label="__('ai.panel.close')"><x-ui.icon name="close" /></x-ui.button>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-ui.badge tone="info" data-ai-timestamp hidden><x-ui.icon name="clock" class="me-1 size-4" /> <span data-ai-timestamp-text></span></x-ui.badge>
        <x-ui.badge tone="success"><x-ui.icon name="file" class="me-1 size-4" /> {{ __('ai.panel.label') }}</x-ui.badge>
    </div>
    <p class="text-sm text-muted">{{ __('ai.panel.official') }}</p>

    <div data-ai-thread role="region" tabindex="0" aria-label="{{ __('ai.title') }}" class="min-h-24 flex-1 space-y-3 overflow-y-auto rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"></div>

    <div data-ai-notice class="space-y-2"></div>

    <div data-ai-starters hidden>
        <p class="mb-2 text-sm font-semibold text-muted" id="ai-starters-label">{{ __('ai.panel.starters') }}</p>
        <ul class="flex flex-wrap gap-2" aria-labelledby="ai-starters-label" data-ai-starter-list></ul>
    </div>

    <div data-ai-actions hidden>
        <div role="group" aria-label="{{ __('ai.panel.actions') }}" class="flex flex-wrap gap-2">
            <x-ui.button type="button" variant="secondary" size="sm" data-ai-action="understood"><x-ui.icon name="check" class="size-4" /> {{ __('ai.understood') }}</x-ui.button>
            <x-ui.button type="button" variant="secondary" size="sm" data-ai-action="simpler"><x-ui.icon name="sparkle" class="size-4" /> {{ __('ai.simplify') }}</x-ui.button>
            <x-ui.button type="button" variant="secondary" size="sm" data-ai-action="still"><x-ui.icon name="help" class="size-4" /> {{ __('ai.still_confused') }}</x-ui.button>
        </div>
    </div>

    <form data-ai-form novalidate class="space-y-1">
        <label for="ai-input" class="sr-only">{{ __('ai.question') }}</label>
        <div class="flex items-end gap-2">
            <x-ui.textarea id="ai-input" name="question" rows="2" dir="auto" data-ai-input aria-describedby="ai-counter" class="resize-none"
                maxlength="{{ $max }}" placeholder="{{ __('ai.panel.placeholder') }}" />
            <x-ui.button type="submit" data-ai-send :aria-label="__('ai.send')"><x-ui.icon name="send" /></x-ui.button>
        </div>
        <p id="ai-counter" class="text-sm text-muted" data-ai-counter>{{ __('ai.characters', ['count' => 0, 'max' => $max]) }}</p>
    </form>

    <div class="sr-only" role="status" aria-live="polite" data-ai-announce></div>

    <template data-ai-tpl="user">
        <div class="flex items-start gap-2"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-assistant text-primary" aria-hidden="true"><x-ui.icon name="user" class="size-4" /></span>
            <p class="min-w-0 flex-1 break-words rounded-card bg-assistant p-3 text-body" dir="auto" data-ai-text></p></div>
    </template>
    <template data-ai-tpl="answer">
        <div class="flex items-start gap-2"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-assistant text-primary" aria-hidden="true"><x-ui.icon name="sparkle" class="size-4" /></span>
            <div class="min-w-0 flex-1 space-y-2 rounded-card border border-border p-3">
                <p class="whitespace-pre-wrap break-words text-body" dir="auto" data-ai-text></p>
                <p class="text-sm text-muted" dir="auto" data-ai-hint hidden></p>
                <div class="flex flex-wrap gap-2" data-ai-pages></div>
            </div></div>
    </template>
    <template data-ai-tpl="typing">
        <x-ui.skeleton :label="__('ai.loading')" />
    </template>
    <template data-ai-tpl="notice">
        <x-ui.alert tone="info"><p data-ai-text></p><a href="#" class="mt-2 inline-flex min-h-11 items-center font-semibold underline" data-ai-request-link hidden></a><x-ui.button type="button" variant="secondary" size="sm" class="mt-2" data-ai-retry hidden>{{ __('ai.panel.retry') }}</x-ui.button></x-ui.alert>
    </template>
</aside>
