{{-- Native dialog manages focus, Escape and inert background. Use side placement for site navigation at every width. --}}
@props(['id', 'title', 'trigger', 'placement' => 'responsive', 'triggerSize' => 'md'])
<x-ui.button command="show-modal" :commandfor="$id" aria-haspopup="dialog" :aria-controls="$id" variant="secondary" :size="$triggerSize">{{ $trigger }}</x-ui.button>
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" {{ $attributes->class([
    'fixed m-0 border border-border bg-surface p-5 text-body shadow-xl',
    'start-auto end-0 inset-y-0 h-dvh max-h-none w-full max-w-[22rem] rounded-s-2xl open:flex open:flex-col backdrop:bg-overlay/55' => $placement === 'side',
    'inset-x-0 inset-y-auto bottom-0 max-h-[85dvh] w-full max-w-none rounded-t-xl backdrop:bg-body/40 sm:inset-x-auto sm:inset-y-0 sm:end-0 sm:max-h-none sm:w-96 sm:rounded-none' => $placement !== 'side',
]) }}>
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="{{ $id }}-title" class="text-lg font-bold">{{ $title }}</h2>
        <form method="dialog"><x-ui.button type="submit" variant="ghost" :aria-label="__('components.close')"><x-ui.icon name="close" /></x-ui.button></form>
    </div>
    {{ $slot }}
</dialog>
