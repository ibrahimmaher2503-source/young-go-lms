{{-- Props: course, signedIn, entitled, purchasable, freeEnrollable, enrollLearners, enrollOwned, showLearnerPicker, seats (CohortAvailability::stateFor). One access call-to-action for the card and the mobile bar. --}}
@if($entitled)
    <a href="{{ route('learn.course', ['course' => $course->id]) }}" data-cta="continue"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('course_page.continue') }}</a>
@elseif(! $signedIn)
    <a href="{{ route('login.page') }}" data-cta="sign-in"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('course_page.sign_in_to_enroll') }}</a>
@elseif((($purchasable ?? false) || ($freeEnrollable ?? false)) && ($seats['full'] ?? false))
    @if($seats['waitlisted'])
        <x-ui.button type="button" size="lg" disabled class="w-full" data-cta="waitlisted">{{ __('cohorts.on_waitlist') }}</x-ui.button>
    @else
        <form method="post" action="{{ route('catalog.waitlist', ['course' => $course->id]) }}" data-waitlist-form>
            @csrf
            <button type="submit" data-cta="waitlist"
                class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('cohorts.join_waitlist') }}</button>
        </form>
    @endif
@elseif($freeEnrollable ?? false)
    <form method="post" action="{{ route('catalog.enroll', ['course' => $course->id]) }}" class="space-y-3" data-enroll-free-form>
        @csrf
        @if(($showLearnerPicker ?? false) && ($enrollLearners ?? collect())->count() > 1)
            <x-ui.form-field for="enroll-learner-id" :label="__('course_page.enroll_for')" :error="$errors->first('child_id')">
                <x-ui.select id="enroll-learner-id" name="child_id" :invalid="$errors->has('child_id')">
                    @foreach($enrollLearners as $option)
                        @php($isOwned = $enrollOwned[$option->getKey()] ?? false)
                        <option value="{{ $option->getKey() }}" @disabled($isOwned)>{{ $option->display_name ?: $option->name }}@if($option->getKey() === auth()->id()) ({{ __('course_page.enroll_myself') }})@endif @if($isOwned) - {{ __('course_page.already_enrolled') }}@endif</option>
                    @endforeach
                </x-ui.select>
            </x-ui.form-field>
        @endif
        <button type="submit" data-cta="enroll-free"
            class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('course_page.enroll_free') }}</button>
    </form>
@elseif($purchasable ?? false)
    <a href="{{ route('checkout.product', ['type' => 'course', 'id' => $course->id]) }}" data-cta="buy"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('checkout.buy_now') }}</a>
@elseif($subscribable ?? false)
    <a href="{{ \App\Domain\Seo\PublicUrl::plans(app()->getLocale()) }}" data-cta="subscribe"
        class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 py-3 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('subscriptions.subscribe_to_access') }}</a>
@else
    <x-ui.button type="button" size="lg" disabled class="w-full" data-cta="soon">{{ __('course_page.enrollment_soon') }}</x-ui.button>
@endif
