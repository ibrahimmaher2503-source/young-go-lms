@props(['courses', 'prices' => [], 'market', 'instructorNames', 'summaries', 'ratings'])
<div class="home-course-grid">
    @foreach($courses as $course)
        <x-learning.course-card :course="$course" :price="$prices[$course->id] ?? null" :market="$market"
            :instructor-names="$instructorNames->only($course->instructors->modelKeys())->filter()->join(', ')"
            :lessons="$summaries[$course->id]['lessons'] ?? null" :minutes="$summaries[$course->id]['minutes'] ?? null"
            :rating="$ratings[$course->id] ?? null" placeholder catalog home class="home-course-card">
            <a href="{{ \App\Domain\Seo\PublicUrl::course($course, app()->getLocale()) }}"
                class="home-button home-course-action">
                {{ __('public-site.view_course') }}<x-ui.icon name="arrow"/>
            </a>
        </x-learning.course-card>
    @endforeach
</div>
