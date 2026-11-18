{{-- Props: course. Heart toggle for the catalog card and the course page. Guests are sent to sign in; the saved state is read once per request. --}}
@props(['course'])
@php
    $learner = app(\App\Domain\Identity\ActingLearner::class)->current();
    $saved = false;
    if ($learner instanceof \App\Models\User) {
        $ids = request()->attributes->get('yg.wishlist.'.$learner->getKey());
        if ($ids === null) {
            $ids = app(\App\Domain\Engagement\Queries\WishlistQuery::class)->savedIds($learner);
            request()->attributes->set('yg.wishlist.'.$learner->getKey(), $ids);
        }
        $saved = in_array((int) $course->getKey(), $ids, true);
    }
    $title = (string) $course->title;
    $button = 'inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
@endphp
@if($learner instanceof \App\Models\User)
    <form method="post" action="{{ route('wishlist.toggle', ['course' => $course->getKey()]) }}" id="wishlist-{{ $course->getKey() }}" {{ $attributes }} data-wishlist>
        @csrf
        <button type="submit" class="{{ $button }} {{ $saved ? 'text-danger' : 'text-body' }}" aria-pressed="{{ $saved ? 'true' : 'false' }}"
            aria-label="{{ $saved ? __('engagement.wishlist_remove', ['title' => $title]) : __('engagement.wishlist_add', ['title' => $title]) }}" data-wishlist-button data-saved="{{ $saved ? '1' : '0' }}">
            <x-ui.icon name="heart" :class="$saved ? 'fill-current' : ''"/>
        </button>
    </form>
@else
    <a href="{{ route('login.page') }}" class="{{ $button }} text-body" aria-label="{{ __('engagement.wishlist_sign_in', ['title' => $title]) }}" id="wishlist-{{ $course->getKey() }}" data-wishlist-login><x-ui.icon name="heart"/></a>
@endif
