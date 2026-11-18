{{-- Props: plans (sellable SubscriptionPlan with prices and courses.course), market, signedIn, seo. --}}
<x-layouts.base :title="$seo['title']" variant="public" wide indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="space-y-6" data-plans-page>
        <header class="space-y-2">
            <h1 class="break-words text-3xl font-extrabold">{{ __('subscriptions.plans_page_title') }}</h1>
            <p class="text-muted">{{ __('subscriptions.plans_page_intro') }}</p>
        </header>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($plans as $plan)
                @php($price = $plan->priceFor($market))
                @php($courses = $plan->courses->map(fn ($c) => $c->course)->filter(fn ($c) => $c?->status === \App\Domain\Catalog\CourseStatus::Published))
                <x-ui.card data-plan="{{ $plan->getKey() }}" class="flex flex-col gap-4">
                    <div class="space-y-1">
                        <h2 class="break-words text-xl font-bold" dir="auto">{{ $plan->name }}</h2>
                        <p class="text-2xl font-extrabold"><x-ui.price :money="\App\Domain\Shared\Money\Money::ofMinor($price->amount_minor, $price->currency)" :market="$market" /> <span class="text-base font-semibold text-muted">{{ __('subscriptions.per_'.$plan->term->value) }}</span></p>
                        @if($plan->grace_days > 0)<p class="text-sm text-muted">{{ __('subscriptions.grace_note', ['days' => $plan->grace_days]) }}</p>@endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <h3 class="font-semibold">{{ __('subscriptions.plan_includes') }}</h3>
                        <ul class="list-disc space-y-1 ps-5">
                            @foreach($courses as $course)
                                <li class="break-words" dir="auto"><x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)])">{{ $course->title }}</x-ui.link></li>
                            @endforeach
                        </ul>
                    </div>
                    @include('catalog._offer-cta', ['type' => 'plan', 'id' => $plan->getKey(), 'signedIn' => $signedIn, 'label' => __('subscriptions.subscribe'), 'signInLabel' => __('subscriptions.sign_in_to_subscribe')])
                </x-ui.card>
            @endforeach
        </div>
    </div>
</x-layouts.base>
