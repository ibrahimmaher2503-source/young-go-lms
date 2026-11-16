{{-- Props: id, label, hint, optional progress (0â€“100). Supply native name, accept and multiple; the caller owns transfer/progress/validation. --}}
@props(['id', 'label', 'hint', 'progress' => null])
<div data-ui-file class="rounded-lg border border-dashed border-border bg-page p-4">
    <label for="{{ $id }}" class="block font-semibold">{{ $label }}</label>
    <p id="{{ $id }}-hint" class="mt-1 text-sm text-muted">{{ $hint }}</p>
    <div class="group relative mt-3 inline-flex min-h-11 max-w-full items-center rounded-md bg-primary px-3 text-sm text-on-primary focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary has-[:disabled]:opacity-50">
        <span aria-hidden="true">{{ __('components.choose') }}</span>
        <input id="{{ $id }}" type="file" aria-describedby="{{ $id }}-hint" {{ $attributes->class('absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed') }}>
    </div>
    <output data-ui-file-names data-empty="{{ __('components.no_file_selected') }}" for="{{ $id }}" class="mt-2 block break-words text-sm text-muted" dir="auto">{{ __('components.no_file_selected') }}</output>
    @if($progress !== null)<x-ui.progress-bar :value="$progress" :label="$label" />@endif
</div>
