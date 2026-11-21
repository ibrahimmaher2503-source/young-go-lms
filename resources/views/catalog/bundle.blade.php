{{-- Props: bundle (sellable Bundle with items.course), price (Money), market, signedIn, seo. --}}
<x-layouts.base :title="$seo['title']" :locale-alternates="$seo['alternates']" variant="public" full indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="public-page-stack mx-auto max-w-7xl py-2 sm:py-4" data-bundle-page>
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => \App\Domain\Seo\PublicUrl::catalog(app()->getLocale())], ['label' => $bundle->name]]"/>

        <header class="space-y-3 border-b border-border pb-5 sm:pb-7">
            <h1 id="bundle-title" class="text-3xl font-extrabold wrap-anywhere sm:text-4xl"><bdi>{{ $bundle->name }}</bdi></h1>
            <p class="max-w-prose text-muted">{{ __('subscriptions.bundle_one_price') }}</p>
        </header>

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:gap-6">
            <x-ui.card aria-label="{{ __('subscriptions.bundle_title') }}" class="space-y-5 shadow-card lg:col-start-2 lg:row-start-1">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-assistant text-primary">
                        <x-ui.icon name="layers"/>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-muted">{{ __('subscriptions.bundle_title') }}</p>
                        <p class="text-3xl font-extrabold tabular-nums sm:text-4xl"><x-ui.price :money="$price" :market="$market" /></p>
                    </div>
                </div>
                @include('catalog._offer-cta', ['type' => 'bundle', 'id' => $bundle->getKey(), 'signedIn' => $signedIn, 'label' => __('subscriptions.bundle_buy'), 'signInLabel' => __('subscriptions.bundle_sign_in')])
            </x-ui.card>

            <x-ui.card aria-labelledby="bundle-courses" class="space-y-3 lg:col-start-1 lg:row-start-1">
                <h2 id="bundle-courses" class="text-xl font-bold">{{ __('subscriptions.bundle_includes') }}</h2>
                <ul role="list" class="divide-y divide-border">
                    @foreach($bundle->items as $item)
                        @if($item->course)
                            <li class="flex min-w-0 items-center gap-3 py-1">
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-assistant text-primary">
                                    <x-ui.icon name="book"/>
                                </span>
                                <x-ui.link :href="\App\Domain\Seo\PublicUrl::course($item->course, app()->getLocale())" class="min-w-0 wrap-anywhere">
                                    <bdi>{{ $item->course->title }}</bdi>
                                </x-ui.link>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.base>
