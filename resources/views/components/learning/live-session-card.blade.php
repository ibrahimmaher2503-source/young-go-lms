{{-- Props: session (LiveSession), state (JoinWindow state), joinUrl (route for the POST that redirects, never the meeting link), viewer.
     The meeting link is never rendered here; the button posts to the join endpoint, which checks access and the window again. --}}
@props(['session', 'state', 'joinUrl', 'viewer' => null, 'replayUrl' => null])
@php
    $opensAt = \App\Domain\Live\JoinWindow::opensAt($session);
    $title = (string) $session->title;
@endphp
<section class="space-y-4 rounded-card border border-border bg-surface p-5 shadow-card sm:p-8" aria-label="{{ __('live.title') }}" data-live-card data-live-state="{{ $state }}"
    data-opens-at="{{ $opensAt->toIso8601String() }}" data-ends-at="{{ $session->ends_at->toIso8601String() }}"
    data-text-starts-in="{{ __('live.starts_in') }}" data-text-joinable="{{ __('live.join_now_hint') }}">
    <div class="flex flex-wrap items-center gap-3">
        <span class="grid size-12 place-items-center rounded-full bg-nav-active-bg text-primary" aria-hidden="true"><x-ui.icon name="video" class="size-6" /></span>
        <div class="min-w-0">
            <p class="text-sm text-muted">{{ __('live.title') }}</p>
            <h3 class="break-words text-xl font-bold">{{ $title }}</h3>
        </div>
    </div>
    <p class="text-lg font-semibold" data-live-time><time datetime="{{ $session->starts_at->toIso8601String() }}">{{ \App\Domain\Live\LiveSessionTime::label($session, $viewer) }}</time></p>
    @if(filled($session->description))<p class="text-muted">{{ $session->description }}</p>@endif

    @if($state === 'upcoming')
        <p class="font-semibold" role="status" data-live-countdown>{{ __('live.opens_at', ['time' => \App\Domain\Live\LiveSessionTime::startsAt($session, $viewer)->setTimestamp($opensAt->getTimestamp())->isoFormat('LLL')]) }}</p>
        <button type="button" class="inline-flex min-h-12 items-center rounded-xl bg-primary px-5 font-bold text-on-primary opacity-60" disabled aria-disabled="true" data-join-disabled>{{ __('live.join') }}</button>
        <form method="POST" action="{{ $joinUrl }}" class="hidden" data-join-form>
            @csrf
            <button type="submit" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary"><x-ui.icon name="video" class="size-5" /> {{ __('live.join') }}</button>
        </form>
    @elseif($state === 'joinable')
        <p class="font-semibold text-success" role="status">{{ __('live.join_now_hint') }}</p>
        <form method="POST" action="{{ $joinUrl }}" data-join-form>
            @csrf
            <button type="submit" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary"><x-ui.icon name="video" class="size-5" /> {{ __('live.join') }}</button>
        </form>
    @elseif($state === 'cancelled')
        <p class="font-semibold text-danger" role="status" data-live-cancelled>{{ __('live.cancelled_notice') }}</p>
    @else
        <p class="text-muted" role="status" data-live-ended>{{ __('live.ended_notice') }}</p>
        @if($replayUrl)<a href="{{ $replayUrl }}" class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-primary px-5 font-bold text-on-primary" data-replay-link><x-ui.icon name="play" class="size-5" /> {{ __('live.replay.watch') }}</a>@endif
    @endif
    @if(session('join_error'))<p class="font-semibold text-danger" role="alert" data-join-error>{{ session('join_error') }}</p>@endif
</section>
