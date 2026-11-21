{{-- Props: plans (sellable SubscriptionPlan with prices and courses.course), market, signedIn, seo. --}}
<x-layouts.base :title="$seo['title']" :locale-alternates="$seo['alternates']" variant="public" full indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="public-page-stack mx-auto max-w-7xl py-2 sm:py-4" data-plans-page>
        <header class="public-band public-band-header grid gap-3 border-b border-border">
            <h1 class="text-3xl font-extrabold wrap-anywhere sm:text-4xl">{{ __('subscriptions.plans_page_title') }}</h1>
            <p class="max-w-prose text-muted">{{ __('subscriptions.plans_page_intro') }}</p>
        </header>

        <div @class(['grid items-stretch gap-x-4 gap-y-5 sm:gap-x-6 sm:gap-y-8', 'max-w-xl' => $plans->count() === 1, 'md:grid-cols-2' => $plans->count() > 1, 'xl:grid-cols-3' => $plans->count() > 2])>
            @foreach($plans as $plan)
                @php
                    $price = $plan->priceFor($market);
                    $courses = $plan->courses->map(fn ($c) => $c->course)->filter(fn ($c) => $c?->status === \App\Domain\Catalog\CourseStatus::Published);
                @endphp
                <x-ui.card data-plan="{{ $plan->getKey() }}" class="flex flex-col gap-5 shadow-card">
                    <div class="space-y-3 rounded-xl bg-assistant p-4 sm:p-5">
                        <h2 class="text-xl font-bold wrap-anywhere sm:text-2xl"><bdi>{{ $plan->name }}</bdi></h2>
                        <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-3xl font-extrabold sm:text-4xl">
                            <x-ui.price :money="\App\Domain\Shared\Money\Money::ofMinor($price->amount_minor, $price->currency)" :market="$market" />
                            <span class="text-sm font-semibold text-muted sm:text-base">{{ __('subscriptions.per_'.$plan->term->value) }}</span>
                        </p>
                        @if($plan->grace_days > 0)<p class="text-sm text-muted">{{ __('subscriptions.grace_note', ['days' => $plan->grace_days]) }}</p>@endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <h3 class="font-bold">{{ __('subscriptions.plan_includes') }}</h3>
                        <ul role="list" class="divide-y divide-border">
                            @foreach($courses as $course)
                                <li class="flex min-w-0 items-center gap-3">
                                    <x-ui.icon name="check" class="size-4 text-primary"/>
                                    <x-ui.link :href="\App\Domain\Seo\PublicUrl::course($course, app()->getLocale())" class="min-w-0 wrap-anywhere">
                                        <bdi>{{ $course->title }}</bdi>
                                    </x-ui.link>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @include('catalog._offer-cta', ['type' => 'plan', 'id' => $plan->getKey(), 'signedIn' => $signedIn, 'label' => __('subscriptions.subscribe'), 'signInLabel' => __('subscriptions.sign_in_to_subscribe')])
                </x-ui.card>
            @endforeach
        </div>

        @php $bundles = $bundles ?? collect(); @endphp
        @if($bundles->isNotEmpty())
            <section class="space-y-4 border-t border-border pt-6" aria-labelledby="plans-bundles-title" data-bundles-section>
                <div class="space-y-1">
                    <h2 id="plans-bundles-title" class="text-2xl font-extrabold sm:text-3xl">{{ __('subscriptions.bundles_section_title') }}</h2>
                    <p class="max-w-prose text-muted">{{ __('subscriptions.bundles_section_intro') }}</p>
                </div>
                <div class="grid items-stretch gap-x-4 gap-y-5 sm:gap-x-6 sm:gap-y-8 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($bundles as $bundle)
                        @php $bundlePrice = $bundle->priceFor($market); @endphp
                        <x-ui.card data-bundle="{{ $bundle->getKey() }}" class="flex flex-col gap-5 shadow-card">
                            <div class="space-y-2 rounded-xl bg-assistant p-4 sm:p-5">
                                <h3 class="text-xl font-bold wrap-anywhere"><bdi>{{ $bundle->name }}</bdi></h3>
                                @if($bundlePrice)
                                    <p class="text-3xl font-extrabold"><x-ui.price :money="\App\Domain\Shared\Money\Money::ofMinor($bundlePrice->amount_minor, $bundlePrice->currency)" :market="$market" /></p>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <h4 class="font-bold">{{ __('subscriptions.bundle_includes') }}</h4>
                                <ul role="list" class="divide-y divide-border">
                                    @foreach($bundle->items as $item)
                                        @if($item->course)
                                            <li class="flex min-w-0 items-center gap-3 py-1">
                                                <x-ui.icon name="book" class="size-4 text-primary"/>
                                                <x-ui.link :href="\App\Domain\Seo\PublicUrl::course($item->course, app()->getLocale())" class="min-w-0 wrap-anywhere">
                                                    <bdi>{{ $item->course->title }}</bdi>
                                                </x-ui.link>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                            <a href="{{ \App\Domain\Seo\PublicUrl::bundle($bundle, app()->getLocale()) }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-primary px-4 py-2.5 font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                {{ __('subscriptions.view_bundle') }}
                            </a>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.base>
