{{-- Props: id, title, trigger. Native dialog manages focus, Escape and inert background. --}}
@props(['id', 'title', 'trigger'])
<x-ui.button command="show-modal" :commandfor="$id" variant="secondary">{{ $trigger }}</x-ui.button>
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" {{ $attributes->class('fixed inset-x-0 inset-y-auto bottom-0 m-0 max-h-[85dvh] w-full max-w-none rounded-t-xl sm:inset-x-auto sm:inset-y-0 sm:end-0 sm:max-h-none sm:w-96 sm:rounded-none border border-border bg-surface p-5 text-body shadow-xl backdrop:bg-body/40') }}>
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="{{ $id }}-title" class="text-lg font-bold">{{ $title }}</h2>
        <form method="dialog"><x-ui.button type="submit" variant="ghost" :aria-label="__('components.close')"><x-ui.icon name="close" /></x-ui.button></form>
    </div>
    {{ $slot }}
</dialog>
