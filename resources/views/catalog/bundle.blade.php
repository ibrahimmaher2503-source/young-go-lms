{{-- Props: bundle (sellable Bundle with items.course), price (Money), market, signedIn, seo. --}}
<x-layouts.base :title="$seo['title']" variant="public" wide indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="space-y-6" data-bundle-page>
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => route('catalog.index')], ['label' => $bundle->name]]"/>

        <section class="space-y-3 rounded-card border border-border bg-surface p-4 shadow-card sm:p-6" aria-labelledby="bundle-title">
            <p class="text-sm font-semibold text-muted">{{ __('subscriptions.bundle_title') }}</p>
            <h1 id="bundle-title" class="break-words text-3xl font-extrabold" dir="auto">{{ $bundle->name }}</h1>
            <p class="text-muted">{{ __('subscriptions.bundle_one_price') }}</p>
            <p class="text-2xl font-extrabold"><x-ui.price :money="$price" :market="$market" /></p>
            <div class="max-w-sm">
                @include('catalog._offer-cta', ['type' => 'bundle', 'id' => $bundle->getKey(), 'signedIn' => $signedIn, 'label' => __('subscriptions.bundle_buy'), 'signInLabel' => __('subscriptions.bundle_sign_in')])
            </div>
        </section>

        <section aria-labelledby="bundle-courses" class="space-y-3">
            <h2 id="bundle-courses" class="text-xl font-bold">{{ __('subscriptions.bundle_includes') }}</h2>
            <ul class="grid gap-3 sm:grid-cols-2">
                @foreach($bundle->items as $item)
                    @if($item->course)
                        <li class="min-w-0 rounded-card border border-border bg-surface p-4" dir="auto">
                            <x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($item->course)])" class="break-words font-semibold">{{ $item->course->title }}</x-ui.link>
                        </li>
                    @endif
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.base>
