@php
    $locale = app()->getLocale();
    $name = $profile->publicName($locale);
    $headline = (string) $profile->getTranslation('headline', $locale, false);
    $bio = (string) $profile->getTranslation('bio', $locale, false);
    $avatar = filled($profile->avatar) ? Illuminate\Support\Facades\Storage::disk('public')->url($profile->avatar) : null;
@endphp
<x-layouts.base :title="$seo['title']" :locale-alternates="$seo['alternates']" variant="public" wide indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="public-page-stack" data-instructor-page>
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => \App\Domain\Seo\PublicUrl::catalog($locale)], ['label' => $name]]"/>

        <section class="grid gap-6 rounded-card border border-border bg-assistant p-6 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-center sm:p-8"
            aria-labelledby="instructor-name" data-section="hero">
            <x-ui.avatar :name="$name" :src="$avatar" class="size-24! bg-surface! text-3xl ring-1 ring-border sm:size-32!" aria-hidden="true"/>
            <div class="min-w-0 space-y-3">
                <h1 id="instructor-name" class="wrap-anywhere text-3xl font-extrabold leading-tight sm:text-4xl">{{ $name }}</h1>
                @if($headline !== '')<p class="max-w-prose wrap-anywhere text-lg leading-relaxed text-muted" dir="auto" data-instructor-headline>{{ $headline }}</p>@endif
                @if($profile->disciplines->isNotEmpty())
                    <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('instructors.disciplines') }}" data-disciplines>
                        @foreach($profile->disciplines as $discipline)
                            <li class="max-w-full">
                                <a href="{{ \App\Domain\Seo\PublicUrl::category($discipline, $locale) }}"
                                    class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-surface px-4 py-2 text-sm font-semibold text-body wrap-anywhere hover:border-primary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    {{ $discipline->name }} <x-ui.icon name="chevron" class="size-4"/>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        @if(filled($bio))
            <section aria-labelledby="instructor-about" class="space-y-3" data-section="bio">
                <h2 id="instructor-about" class="text-xl font-bold sm:text-2xl">{{ __('instructors.about') }}</h2>
                <p class="max-w-prose whitespace-pre-line wrap-anywhere leading-relaxed text-muted" dir="auto">{{ $bio }}</p>
            </section>
        @endif

        <section aria-labelledby="instructor-courses" class="min-w-0 space-y-5 border-t border-border pt-6" data-section="courses">
            <h2 id="instructor-courses" class="text-xl font-bold sm:text-2xl">{{ __('instructors.courses') }}</h2>
            @if($courses->isEmpty())
                <x-ui.empty-state :title="__('instructors.no_courses')" icon="book"/>
            @else
                <div class="public-card-grid grid gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($courses as $course)
                        <x-learning.course-card :course="$course" :instructor-names="''" placeholder catalog>
                            <x-ui.link :href="\App\Domain\Seo\PublicUrl::course($course, $locale)" class="text-sm" data-course-link>{{ __('catalog.view_course') }}</x-ui.link>
                        </x-learning.course-card>
                    @endforeach
                </div>
                @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
            @endif
        </section>
    </div>
</x-layouts.base>
