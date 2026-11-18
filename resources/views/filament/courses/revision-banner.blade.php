@php
    use App\Domain\Catalog\CourseStatus;
    use App\Domain\Catalog\Revisions\Queries\CourseRevisions;
    use App\Domain\Catalog\Revisions\RevisionAccess;
    use App\Filament\Resources\Courses\CourseResource;

    $viewer = auth()->user();
    $live = $course instanceof \App\Models\Course && $course->status === CourseStatus::Published && $viewer !== null;
    $revision = $live ? CourseRevisions::open($course) : null;
    $required = $live && RevisionAccess::requiresRevision($viewer, $course);
@endphp
@if ($live && ($revision || $required))
    <div class="rounded-card border border-border bg-surface p-4 shadow-card" role="status" data-revision-banner="{{ $revision?->status->value ?? 'required' }}">
        <p class="font-semibold">
            @if ($revision)
                {{ __('course_revisions.banner_open', ['number' => $revision->number, 'status' => __('course_revisions.state.'.$revision->status->value)]) }}
            @else
                {{ __('course_revisions.banner_required') }}
            @endif
        </p>
        <p class="text-sm text-muted">{{ __('course_revisions.banner_live') }}</p>
        <a class="mt-2 inline-block font-semibold underline" href="{{ CourseResource::getUrl('revision', ['record' => $course]) }}">{{ __('course_revisions.banner_link') }}</a>
    </div>
@endif
