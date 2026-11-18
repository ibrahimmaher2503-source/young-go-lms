<x-layouts.base variant="public" indexable full>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    @php
        $locale = app()->getLocale();
        $catalog = \App\Domain\Seo\PublicUrl::catalog($locale);
        $hero = $blocks->firstWhere('type', 'hero');
        $steps = $blocks->firstWhere('type', 'how_it_works');
    @endphp
    <div class="space-y-5">
        @unless($hero)<h1 class="text-3xl font-bold">{{ __('app.name') }}</h1>@endunless
        @foreach($blocks as $block)
            @if($block->is($hero))
                <section data-block-type="hero" class="grid items-center gap-6 rounded-xl border border-border bg-surface p-4 shadow-sm lg:grid-cols-[.86fr_1fr] lg:p-5">
                    <div class="min-w-0 space-y-4 px-2 py-3 sm:px-4">
                        <h1 class="break-words text-3xl leading-tight font-extrabold sm:text-4xl xl:text-5xl">{{ $block->title }}</h1>
                        @if(filled($block->body))<p class="max-w-prose whitespace-pre-line break-words text-lg leading-relaxed text-muted">{{ $block->body }}</p>@endif
                        <div class="flex flex-wrap gap-3 pt-2 sm:pt-4">
                            <a href="{{ $catalog }}" class="inline-flex min-h-12 items-center gap-3 rounded-lg bg-primary px-5 py-2 text-base font-bold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('public-site.browse') }}<x-ui.icon name="arrow" /></a>
                            @if($steps)<a href="#home-block-{{ $steps->id }}" class="inline-flex min-h-12 items-center rounded-lg border border-primary px-5 py-2 font-bold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('public-site.how_link') }}</a>@endif
                        </div>
                    </div>
                    <img src="{{ asset('images/public-home-hero-640.webp') }}"
                        srcset="{{ asset('images/public-home-hero-640.webp') }} 640w, {{ asset('images/public-home-hero-1200.webp') }} 1200w"
                        sizes="(min-width: 1024px) 53vw, 100vw" width="1200" height="400" alt="" fetchpriority="high"
                        class="aspect-[3/1] w-full rounded-xl object-cover">
                </section>
            @elseif($block->type === 'categories' && $categories->isNotEmpty())
                <section data-block-type="categories" class="rounded-xl border border-border bg-surface p-2.5 shadow-sm">
                    <h2 class="sr-only">{{ $block->title }}</h2>
                    @if(filled($block->body))<p class="p-2 text-muted">{{ $block->body }}</p>@endif
                    <div class="grid grid-cols-2 gap-2 lg:grid-cols-4 lg:gap-3">
                        @foreach($categories as $category)
                            @php($icon = match ($category->slug_en) { 'drawing' => 'sparkle', 'voice-and-dubbing' => 'video', 'animation' => 'play-circle', 'acting', 'theatre', 'melodrama' => 'users', default => 'book' })
                            <a class="flex min-h-16 min-w-0 flex-col items-start justify-center gap-2 rounded-lg bg-assistant px-3 py-2 font-bold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary sm:flex-row sm:items-center sm:justify-start sm:gap-4 sm:text-lg" href="{{ \App\Domain\Seo\PublicUrl::category($category, $locale) }}">
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-surface text-primary"><x-ui.icon :name="$icon" /></span>
                                <span class="min-w-0 break-words">{{ $category->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @elseif($block->type === 'featured_courses')
                <section data-block-type="featured_courses" class="space-y-3">
                    <h2 class="px-1 text-2xl font-extrabold">{{ $block->title }}</h2>
                    @if(filled($block->body))<p class="whitespace-pre-line break-words px-1 text-muted">{{ $block->body }}</p>@endif
                    @if($featured->isEmpty())
                        <x-ui.link :href="$catalog">{{ __('public-site.browse') }}</x-ui.link>
                    @else
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($featured as $course)
                                <x-learning.course-card :course="$course" compact>
                                    <x-ui.link :href="\App\Domain\Seo\PublicUrl::course($course, $locale)">{{ __('public-site.view_course') }}</x-ui.link>
                                </x-learning.course-card>
                            @endforeach
                        </div>
                    @endif
                </section>
            @elseif($block->type === 'how_it_works')
                <section id="home-block-{{ $block->id }}" data-block-type="how_it_works" class="scroll-mt-4 space-y-4 rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-5">
                    <h2 class="text-2xl font-extrabold">{{ $block->title }}</h2>
                    @if(filled($block->body))<p class="whitespace-pre-line break-words text-muted">{{ $block->body }}</p>@endif
                    <ol class="grid list-none gap-3 md:grid-cols-3">
                        @foreach(['choose' => 'book', 'practice' => 'play-circle', 'ask' => 'help'] as $step => $icon)
                            <li class="flex items-start gap-3 rounded-xl border border-border bg-surface p-3">
                                <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-assistant text-primary"><x-ui.icon :name="$icon" /></span>
                                <div class="space-y-1">
                                    <h3 class="font-bold">{{ __('public-site.'.$step) }}</h3>
                                    <p class="text-sm leading-relaxed text-muted">{{ __('public-site.'.$step.'_detail') }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @else
                <section data-block-type="{{ $block->type }}" class="space-y-3 rounded-xl border border-border bg-surface p-5 shadow-sm">
                    <h2 class="break-words text-2xl font-bold">{{ $block->title }}</h2>
                    @if(filled($block->body))<p class="whitespace-pre-line break-words text-muted">{{ $block->body }}</p>@endif
                    @if($block->type === 'cta')<x-ui.link :href="$catalog">{{ __('public-site.browse') }}</x-ui.link>@endif
                </section>
            @endif
        @endforeach
    </div>
</x-layouts.base>
