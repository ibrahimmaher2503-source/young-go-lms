@php
    $locale = app()->getLocale();
    $name = $profile->publicName($locale);
    $headline = (string) $profile->getTranslation('headline', $locale, false);
    $bio = (string) $profile->getTranslation('bio', $locale, false);
    $avatar = filled($profile->avatar) ? Illuminate\Support\Facades\Storage::disk('public')->url($profile->avatar) : null;
@endphp
<x-layouts.base :title="$seo['title']" variant="public" wide indexable>
    <x-slot:head><x-seo.head :seo="$seo"/></x-slot:head>

    <div class="space-y-6" data-instructor-page>
        <x-ui.breadcrumb :items="[['label' => __('catalog.title'), 'url' => route('catalog.index')], ['label' => $name]]"/>

        <section class="flex flex-wrap items-start gap-5 rounded-card border border-border bg-surface p-4 shadow-card sm:p-6" aria-labelledby="instructor-name" data-section="hero">
            <x-ui.avatar :name="$name" :src="$avatar" class="!size-24 text-3xl"/>
            <div class="min-w-0 flex-1 space-y-3">
                <h1 id="instructor-name" class="break-words text-3xl font-extrabold">{{ $name }}</h1>
                @if($headline !== '')<p class="break-words text-lg" data-instructor-headline>{{ $headline }}</p>@endif
                @if($profile->disciplines->isNotEmpty())
                    <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('instructors.disciplines') }}" data-disciplines>
                        @foreach($profile->disciplines as $discipline)
                            <li><a href="{{ route('categories.show', ['category' => \App\Domain\Catalog\Queries\PublicCategory::keyFor($discipline, $locale)]) }}" class="inline-flex min-h-11 items-center rounded-full border border-border bg-page px-4 text-sm font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ $discipline->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        @if(filled($bio))
            <x-ui.card :title="__('instructors.about')" data-section="bio"><p class="break-words" dir="auto">{!! nl2br(e($bio)) !!}</p></x-ui.card>
        @endif

        <section aria-labelledby="instructor-courses" class="min-w-0 space-y-4" data-section="courses">
            <h2 id="instructor-courses" class="text-xl font-bold">{{ __('instructors.courses') }}</h2>
            @if($courses->isEmpty())
                <x-ui.card><p>{{ __('instructors.no_courses') }}</p></x-ui.card>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($courses as $course)
                        <x-learning.course-card :course="$course"><x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)])" data-course-link>{{ __('catalog.view_course') }}</x-ui.link></x-learning.course-card>
                    @endforeach
                </div>
                @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
            @endif
        </section>
    </div>
</x-layouts.base>
