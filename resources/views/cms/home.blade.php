<x-layouts.base variant="public" indexable full homepage :home-instructors="$homeInstructors->isNotEmpty()">
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>
    @php
        $locale = app()->getLocale();
        $catalog = \App\Domain\Seo\PublicUrl::catalog($locale);
        $hero = $blocks->firstWhere('type', 'hero');
        $heroCourse = $featured->first();
        $heroAccent = __('public-site.hero_accent');
        $steps = $blocks->firstWhere('type', 'how_it_works');
        $stepsLink = $steps ? '#home-block-'.$steps->id : null;
        $firstDisciplines = $blocks->firstWhere('type', 'categories');
        $firstInstructors = $blocks->firstWhere('type', 'instructors');
        $formats = ['recorded' => 'play-circle', 'live' => 'video', 'hybrid' => 'layers'];
        $disciplineOrder = array_flip(['drawing', 'creative-skills', 'animation', 'theatre', 'melodrama', 'voice-and-dubbing']);
        $art = asset('images/homepage');
        $primary = 'home-button home-button-primary';
        $textLink = 'home-text-link';
    @endphp
    <div class="public-home" data-public-home>
        @unless($hero)<h1 class="home-title">{{ __('app.name') }}</h1>@endunless

        @foreach($blocks as $block)
            @if($block->is($hero))
                <section class="home-hero" aria-labelledby="home-hero-title" data-block-type="hero">
                    <div class="home-hero-copy" data-home-enter="copy">
                        <h1 id="home-hero-title" class="public-hero-title">@if($block->title === __('public-site.hero') && str_ends_with($block->title, $heroAccent)){{ substr($block->title, 0, -strlen($heroAccent)) }}<span>{{ $heroAccent }}</span>@else{{ $block->title }}@endif</h1>
                        @if(filled($block->body))<p class="home-hero-intro">{{ $block->body }}</p>@endif
                        <div class="home-hero-actions">
                            <a href="{{ $catalog }}" class="{{ $primary }}">{{ __('public-site.browse') }}<x-ui.icon name="arrow"/></a>
                            @if($stepsLink)
                                <a href="{{ $stepsLink }}" class="home-button home-button-secondary">{{ __('public-site.how_link') }}<x-ui.icon name="chevron-down"/></a>
                            @endif
                        </div>
                        @if($formatCounts->isNotEmpty())
                            <ul class="home-hero-formats" aria-label="{{ __('public-site.formats') }}">
                                @foreach($formats as $format => $icon)
                                    @if($formatCounts->has($format))
                                        <li><x-ui.icon :name="$icon"/>{{ __('courses.'.$format) }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="public-hero-media home-hero-media" data-home-enter="media">
                        <img src="{{ asset('images/public-home-studio-640.webp') }}"
                            srcset="{{ asset('images/public-home-studio-640.webp') }} 640w, {{ asset('images/public-home-studio-1200.webp') }} 1200w"
                            sizes="(min-width: 76.5rem) 668px, (min-width: 50rem) 55vw, 94vw" width="1200" height="800" alt="" fetchpriority="high" decoding="async">
                        @if($heroCourse)
                            <a href="{{ \App\Domain\Seo\PublicUrl::course($heroCourse, $locale) }}" class="home-hero-course" data-home-hero-course>
                                <span class="home-hero-course-icon"><x-ui.icon name="book"/></span>
                                <strong>{{ $heroCourse->title }}</strong>
                                <x-ui.icon name="arrow"/>
                            </a>
                        @endif
                    </div>
                </section>
                <ul class="home-stats" aria-label="{{ __('public-site.platform_stats') }}">
                    <li>
                        <a href="{{ $categories->isNotEmpty() ? '#home-disciplines' : $catalog.'#catalog-filters' }}" class="home-stat">
                            <x-ui.icon name="star"/><strong data-home-stat="categories">{{ $disciplineCount }}</strong><span>{{ __('public-site.stat_categories') }}</span>
                        </a>
                    </li>
                    <li>
                        @if($formatCounts->isNotEmpty())
                            <details class="home-format-details">
                                <summary class="home-stat">
                                    <x-ui.icon name="layers"/><strong data-home-stat="courses">{{ $formatCounts->sum() }}</strong><span>{{ __('public-site.stat_courses') }}</span>
                                </summary>
                                <nav class="home-format-links" aria-label="{{ __('public-site.formats') }}">
                                    @foreach($formats as $format => $icon)
                                        @if($count = $formatCounts->get($format))
                                            <a href="{{ route('public.catalog.index', ['publicLocale' => $locale, 'format' => $format]) }}" data-home-format="{{ $format }}">
                                                <x-ui.icon :name="$icon"/>{{ __('courses.'.$format) }} <bdi>{{ $count }}</bdi>
                                            </a>
                                        @endif
                                    @endforeach
                                </nav>
                            </details>
                        @else
                            <a href="{{ $catalog }}" class="home-stat"><x-ui.icon name="layers"/><strong data-home-stat="courses">0</strong><span>{{ __('public-site.stat_courses') }}</span></a>
                        @endif
                    </li>
                    <li>
                        <div class="home-stat"><x-ui.icon name="play-circle"/><strong>{{ $instructorCount }}</strong><span>{{ __('public-site.stat_instructors') }}</span></div>
                    </li>
                    <li>
                        <div class="home-stat"><x-ui.icon name="graduate"/><strong>{{ $learnerCount }}</strong><span>{{ __('public-site.stat_learners') }}</span></div>
                    </li>
                </ul>
            @elseif($block->type === 'categories')
                @if($categories->isNotEmpty())
                    <section data-block-type="categories" @if($block->is($firstDisciplines)) id="home-disciplines" @endif data-home-reveal>
                        <div class="home-section-heading">
                            <div>
                                <h2 class="home-title">{{ $block->title }}</h2>
                                <p>{{ filled($block->body) ? $block->body : __('public-site.disciplines_intro') }}</p>
                            </div>
                            <a href="{{ $catalog.'#catalog-filters' }}" class="{{ $textLink }}"><x-ui.icon name="arrow"/>{{ __('public-site.browse_disciplines') }}</a>
                        </div>
                        <div class="home-disciplines">
                            @foreach($categories->sortBy(fn ($category) => $disciplineOrder[$category->slug_en] ?? count($disciplineOrder))->take(6) as $category)
                                @php
                                    $cover = filled($category->image)
                                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($category->image)
                                        : (isset($disciplineOrder[$category->slug_en]) ? $art.'/'.$category->slug_en.'.webp' : null);
                                @endphp
                                <a class="home-discipline" href="{{ \App\Domain\Seo\PublicUrl::category($category, $locale) }}">
                                    <div class="home-discipline-media">
                                        @if($cover)<img src="{{ $cover }}" alt="" width="127" height="82" loading="lazy" decoding="async">@endif
                                    </div>
                                    <span class="home-discipline-title">{{ $category->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @elseif($block->type === 'featured_courses')
                <section data-block-type="featured_courses" data-home-reveal>
                    <div class="home-section-heading">
                        <div>
                            <h2 class="home-title">{{ $block->title }}</h2>
                            <p>{{ filled($block->body) ? $block->body : __('public-site.featured_intro') }}</p>
                        </div>
                        @if($featured->isEmpty())<a href="{{ $catalog }}" class="{{ $textLink }}">{{ __('public-site.browse') }}<x-ui.icon name="arrow"/></a>@endif
                    </div>
                    @if($featured->isNotEmpty())
                        <div class="home-featured">
                            <div class="home-featured-promo">
                                <span class="home-step-icon"><x-ui.icon name="award"/></span>
                                <p>{{ __('public-site.featured_promo') }}</p>
                                <a href="{{ $catalog }}" class="{{ $primary }}">{{ __('public-site.view_all_courses') }}<x-ui.icon name="arrow"/></a>
                            </div>
                            <x-public.home-course-grid :courses="$featured" :prices="$featuredPrices" :market="$market" :instructor-names="$courseInstructorNames" :summaries="$courseSummaries" :ratings="$courseRatings"/>
                        </div>
                    @endif
                </section>
            @elseif($block->type === 'how_it_works')
                <section id="home-block-{{ $block->id }}" data-block-type="how_it_works" data-home-reveal class="home-steps">
                    <div class="home-steps-copy">
                        <h2 class="home-title">{{ $block->title }}</h2>
                        <p>{{ filled($block->body) ? $block->body : __('public-site.steps_intro') }}</p>
                    </div>
                    <ol class="home-steps-list">
                        @foreach(['choose' => 'play-circle', 'follow' => 'calendar', 'practice' => 'pencil', 'create' => 'trophy'] as $step => $icon)
                            <li>
                                <span class="home-step-icon"><x-ui.icon :name="$icon"/></span>
                                <h3>{{ __('public-site.'.$step) }}</h3>
                                <p>{{ __('public-site.'.$step.'_detail') }}</p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @elseif($block->type === 'free_courses')
                @if($free)
                    @php
                        $freeCover = $free->cover_image ?: $free->thumbnail_path;
                        $freeSummary = $courseSummaries[$free->id] ?? ['lessons' => 0, 'minutes' => 0];
                    @endphp
                    <section data-block-type="free_courses" data-home-reveal class="home-free">
                        @if($freeCover || $free->slug_en === 'kids-art-colour-and-fun')
                            <img class="home-free-art" src="{{ $freeCover ? \Illuminate\Support\Facades\Storage::disk('public')->url($freeCover) : $art.'/free-painting.webp' }}"
                                alt="" width="640" height="360" loading="lazy" decoding="async">
                        @endif
                        <div class="home-free-copy">
                            <a href="{{ route('public.catalog.index', ['publicLocale' => $locale, 'access' => 'free']) }}" class="home-chip" aria-description="{{ __('public-site.browse_free') }}"><x-ui.icon name="gift"/>{{ $block->title }}</a>
                            <h2 class="home-title">{{ $free->title }}</h2>
                            @if($description = filled($block->body) ? $block->body : $free->short_description)<p>{{ $description }}</p>@endif
                            @if($freeSummary['lessons'] > 0)
                                <div class="home-free-meta">
                                    <span><x-ui.icon name="layers"/>{{ trans_choice('curriculum.lessons_count_choice', $freeSummary['lessons']) }}</span>
                                    @if($freeSummary['minutes'] > 0)<span><x-ui.icon name="clock"/>{{ trans_choice('curriculum.minutes_choice', $freeSummary['minutes']) }}</span>@endif
                                </div>
                            @endif
                            <a href="{{ \App\Domain\Seo\PublicUrl::course($free, $locale) }}" class="{{ $primary }}">{{ __('public-site.start_free') }}<x-ui.icon name="arrow"/></a>
                        </div>
                    </section>
                @endif
            @elseif($block->type === 'instructors')
                @if($homeInstructors->isNotEmpty() || filled($block->body))
                    <section @if($block->is($firstInstructors)) id="home-instructors" @endif data-block-type="instructors" data-home-reveal>
                        <div class="home-section-heading">
                            <div>
                                <h2 class="home-title">{{ $block->title }}</h2>
                                <p>{{ filled($block->body) ? $block->body : __('public-site.instructors_intro') }}</p>
                            </div>
                        </div>
                        <div class="home-instructors">
                            @foreach($homeInstructors as $profile)
                                @php
                                    $name = $profile->publicName($locale);
                                    $avatar = filled($profile->avatar) ? \Illuminate\Support\Facades\Storage::disk('public')->url($profile->avatar) : null;
                                    $specialization = $profile->disciplines->pluck('name')->filter()->join(' · ') ?: $profile->headline;
                                @endphp
                                <a href="{{ \App\Domain\Seo\PublicUrl::instructor($profile, $locale) }}" class="home-instructor">
                                    <div class="home-instructor-image" aria-hidden="true">
                                        @if($avatar)
                                            <img src="{{ $avatar }}" alt="" width="320" height="160" loading="lazy" decoding="async">
                                        @else
                                            <x-ui.avatar :name="$name"/>
                                        @endif
                                    </div>
                                    <h3>{{ $name }}</h3>
                                    @if(filled($specialization))<p><bdi>{{ $specialization }}</bdi></p>@endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @elseif($block->type === 'blog')
                @if($posts->isNotEmpty())
                    <section data-block-type="blog" data-home-reveal>
                        <div class="home-section-heading">
                            <div>
                                <h2 class="home-title">{{ $block->title }}</h2>
                                @if(filled($block->body))<p>{{ $block->body }}</p>@endif
                            </div>
                            <a href="{{ \App\Domain\Seo\PublicUrl::blog($locale) }}" class="{{ $textLink }}">{{ __('public-site.read_blog') }}<x-ui.icon name="arrow"/></a>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($posts as $post)
                                @include('blog._card', ['post' => $post, 'href' => \App\Domain\Seo\PublicUrl::blogPost($post, $locale)])
                            @endforeach
                        </div>
                    </section>
                @endif
            @elseif($block->type === 'newsletter')
                <section data-block-type="newsletter" data-home-reveal class="home-newsletter">
                    <img class="home-newsletter-art" src="{{ asset('images/public-info/catalog-band.webp') }}" alt="" width="816" height="300" loading="lazy" decoding="async">
                    <div class="home-newsletter-copy">
                        <h2 class="home-title">{{ $block->title }}</h2>
                        <p>{{ filled($block->body) ? $block->body : __('contact.newsletter.intro') }}</p>
                        <x-marketing.newsletter-form source="home" :id-prefix="'home-newsletter-'.$block->id" compact/>
                        <noscript><a class="home-text-link" href="{{ \App\Domain\Seo\PublicUrl::contact($locale).'#contact-newsletter' }}">{{ __('public-site.newsletter_link') }}</a></noscript>
                    </div>
                </section>
            @else
                <section data-block-type="{{ $block->type }}" data-home-reveal class="space-y-3 rounded-xl border border-border bg-surface p-5">
                    <h2 class="home-title">{{ $block->title }}</h2>
                    @if(filled($block->body))<p class="whitespace-pre-line wrap-anywhere text-muted">{{ $block->body }}</p>@endif
                    @if($block->type === 'cta')<a href="{{ $catalog }}" class="{{ $primary }}">{{ __('public-site.browse') }}</a>@endif
                </section>
            @endif
        @endforeach
    </div>
</x-layouts.base>
