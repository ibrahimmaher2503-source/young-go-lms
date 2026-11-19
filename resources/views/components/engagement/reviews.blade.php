{{-- Props: course. The public reviews area of the course page: published reviews only, the rating summary, and the viewer's own form when they are entitled. Hidden until there is something to show. --}}
@props(['course'])
@php
    $summary = \App\Domain\Engagement\ReviewSummary::for($course);
    $reviews = app(\App\Domain\Engagement\Queries\PublishedReviews::class)->forCourse($course);
    $learner = app(\App\Domain\Identity\ActingLearner::class)->current();
    $eligibility = app(\App\Domain\Engagement\Queries\ReviewEligibility::class)->for($learner, $course);
    $own = $eligibility['review'];
    $canReview = $eligibility['state'] === \App\Domain\Engagement\Queries\ReviewEligibility::CAN_REVIEW;
    $visible = $reviews->isNotEmpty() || $canReview;
    $oldRating = (int) old('rating', $own?->rating ?? 0);
    $min = (int) config('engagement.reviews.body_min');
    $max = (int) config('engagement.reviews.body_max');
@endphp
<section id="reviews" data-reviews-slot @unless($visible) hidden @endunless aria-label="{{ __('course_page.reviews') }}">
    @if($visible)
        <x-ui.card :title="__('course_page.reviews')" class="space-y-4">
            @if($summary['count'] > 0)
                <p class="flex flex-wrap items-center gap-2" data-reviews-summary>
                    <x-engagement.stars :value="$summary['average']" :label="__('engagement.average_label', ['value' => $summary['average']])" class="text-xl"/>
                    <span class="font-bold"><bdi dir="ltr">{{ $summary['average'] }}</bdi></span>
                    <span class="text-muted">{{ trans_choice('engagement.count', $summary['count'], ['count' => $summary['count']]) }}</span>
                </p>
            @endif

            @if($reviews->isNotEmpty())
                <ul class="divide-y divide-border">
                    @foreach($reviews as $review)
                        <li class="space-y-1 py-3" data-review>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <x-engagement.stars :value="$review->rating"/>
                                <span class="break-words font-semibold" data-review-author>{{ $review->author_name }}</span>
                                <time class="text-sm text-muted" datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->locale(app()->getLocale())->isoFormat('LL') }}</time>
                            </div>
                            <p class="break-words" dir="auto">{!! nl2br(e($review->body)) !!}</p>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if(session('review_notice'))<x-ui.alert tone="success" role="status" data-review-notice>{{ session('review_notice') }}</x-ui.alert>@endif

            @if($canReview)
                <details class="border-t border-border pt-4" data-review-disclosure @if($errors->hasAny(['review', 'rating', 'body']) || old('body') !== null) open @endif>
                <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-primary px-4 font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary [&::-webkit-details-marker]:hidden" data-review-toggle><x-ui.icon name="send" class="size-4" />{{ $own === null ? __('engagement.write') : __('engagement.edit') }}</summary>
                <form method="post" action="{{ route('reviews.store', ['course' => $course->getKey()]) }}" class="mt-4 space-y-4" data-review-form novalidate>
                    @csrf
                    @if($own?->status === \App\Domain\Engagement\ReviewStatus::Pending)
                        <x-ui.alert tone="info" data-review-state="pending">{{ __('engagement.state_pending') }}</x-ui.alert>
                    @elseif($own?->status === \App\Domain\Engagement\ReviewStatus::Published)
                        <x-ui.alert tone="success" data-review-state="published">{{ __('engagement.state_published') }}</x-ui.alert>
                    @elseif($own?->status === \App\Domain\Engagement\ReviewStatus::Rejected)
                        <x-ui.alert tone="warning" data-review-state="rejected">{{ __('engagement.state_rejected', ['reason' => (string) $own->moderation_reason]) }}</x-ui.alert>
                    @endif
                    @foreach(['review', 'rating', 'body'] as $field)
                        @error($field)<x-ui.alert tone="danger" role="alert" data-review-error="{{ $field }}">{{ $message }}</x-ui.alert>@enderror
                    @endforeach
                    <fieldset class="space-y-2">
                        <legend class="font-semibold">{{ __('engagement.rating') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @for($i = 1; $i <= 5; $i++)
                                <label class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center gap-1 rounded-lg border border-border bg-surface px-3 font-semibold has-[:checked]:bg-primary has-[:checked]:text-on-primary has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary">
                                    <input type="radio" name="rating" value="{{ $i }}" class="sr-only" @checked($oldRating === $i) required>
                                    <span aria-hidden="true">{{ $i }} &#9733;</span><span class="sr-only">{{ __('engagement.stars', ['value' => $i]) }}</span>
                                </label>
                            @endfor
                        </div>
                    </fieldset>
                    <x-ui.form-field for="review-body" :label="__('engagement.body')" :help="__('engagement.body_help', ['min' => $min, 'max' => $max])">
                        <x-ui.textarea id="review-body" name="body" rows="4" minlength="{{ $min }}" maxlength="{{ $max }}" required aria-describedby="review-body-help" dir="auto">{{ old('body', $own?->body) }}</x-ui.textarea>
                    </x-ui.form-field>
                    <p class="text-sm text-muted">{{ __('engagement.moderation_note') }}</p>
                    <x-ui.button type="submit">{{ $own === null ? __('engagement.submit') : __('engagement.resubmit') }}</x-ui.button>
                </form>
                </details>
            @endif
        </x-ui.card>
    @endif
</section>
