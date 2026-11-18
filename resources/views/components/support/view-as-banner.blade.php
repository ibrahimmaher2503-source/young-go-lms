{{-- Props: session (ViewAsSession with learner). Persistent banner of a support view-as session (ED-027): who is viewed, until when, and the only way out. --}}
@props(['session'])
<div role="status" class="sticky top-0 z-40 border-b-4 border-primary bg-accent font-semibold text-on-accent shadow-md" data-view-as-banner>
    <div class="mx-auto flex max-w-[90rem] flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-8">
        <div class="min-w-0">
            <p class="break-words font-bold">{{ __('support.banner', ['name' => $session->learner?->display_name ?: $session->learner?->name]) }}</p>
            <p class="text-sm">{{ __('support.banner_limits', ['time' => $session->expires_at->isoFormat('LT')]) }}</p>
        </div>
        <form method="POST" action="{{ route('support.view-as.stop') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm" data-view-as-stop>{{ __('support.stop') }}</x-ui.button>
        </form>
    </div>
</div>
