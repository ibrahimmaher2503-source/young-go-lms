{{-- Props: course, signedIn, entitled, purchasable, seats (CohortAvailability::stateFor). One access call-to-action for the card and the mobile bar. Checkout and self-enrolment arrive with P4/P8. --}}
@if($entitled)
    <a href="{{ route('learn.course', ['course' => $course->id]) }}" data-cta="continue"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('course_page.continue') }}</a>
@elseif(! $signedIn)
    <a href="{{ route('login.page') }}" data-cta="sign-in"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('course_page.sign_in_to_enroll') }}</a>
@elseif(($purchasable ?? false) && ($seats['full'] ?? false))
    @if($seats['waitlisted'])
        <x-ui.button type="button" size="lg" disabled class="w-full" data-cta="waitlisted">{{ __('cohorts.on_waitlist') }}</x-ui.button>
    @else
        <form method="post" action="{{ route('catalog.waitlist', ['course' => $course->id]) }}" data-waitlist-form>
            @csrf
            <button type="submit" data-cta="waitlist"
                class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('cohorts.join_waitlist') }}</button>
        </form>
    @endif
@elseif($purchasable ?? false)
    <a href="{{ route('checkout.product', ['type' => 'course', 'id' => $course->id]) }}" data-cta="buy"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('checkout.buy_now') }}</a>
@else
    <x-ui.button type="button" size="lg" disabled class="w-full" data-cta="soon">{{ __('course_page.enrollment_soon') }}</x-ui.button>
@endif
