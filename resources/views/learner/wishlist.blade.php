<x-layouts.learner :title="__('engagement.wishlist_title')" wide>
    <div class="space-y-6">
        <h1 class="text-3xl font-bold">{{ __('engagement.wishlist_title') }}</h1>

        @if($courses->isEmpty())
            <x-ui.empty-state :title="__('engagement.wishlist_empty')" :description="__('engagement.wishlist_empty_help')" data-empty>
                <x-ui.link :href="route('catalog.index')">{{ __('learner.browse') }}</x-ui.link>
            </x-ui.empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
                    <x-learning.course-card :course="$course" :wishlist="true">
                        <x-ui.link :href="route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($course)])" data-course-link>{{ __('catalog.view_course') }}</x-ui.link>
                    </x-learning.course-card>
                @endforeach
            </div>
            @if($courses->hasPages())<x-ui.pagination :paginator="$courses"/>@endif
        @endif
    </div>
</x-layouts.learner>
