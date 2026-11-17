{{-- Props: id, title, trigger. Native dialog manages focus, Escape and inert background. --}}
@props(['id', 'title', 'trigger', 'triggerVariant' => 'secondary'])
<x-ui.button command="show-modal" :commandfor="$id" variant="{{ $triggerVariant }}">{{ $trigger }}</x-ui.button>
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" {{ $attributes->class('m-auto w-[min(calc(100%-2rem),32rem)] max-h-[calc(100%-2rem)] rounded-xl border border-border bg-surface p-5 text-body shadow-xl backdrop:bg-body/40') }}>
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 id="{{ $id }}-title" class="text-lg font-bold">{{ $title }}</h2>
        <form method="dialog"><x-ui.button type="submit" variant="ghost" :aria-label="__('components.close')"><x-ui.icon name="close" /></x-ui.button></form>
    </div>
    {{ $slot }}
</dialog>
