@php
    $lessonModel = $item->itemable;
    $readiness = \App\Domain\AI\Ingestion\DocumentReadiness::fromStatus($lessonModel->curriculumDocuments->first()?->status);
    $reviewable = $lessonModel->curriculumDocuments->first() !== null && auth()->user()?->can('lesson_document.approve');
@endphp
<span class="yg-cur-badge" title="{{ __('media.readiness.title') }}" data-readiness="{{ $readiness->value }}">
    <span aria-hidden="true">{{ $readiness->icon() }}</span>&nbsp;{{ $readiness->label() }}
</span>
@if($reviewable)
    <a class="yg-cur-btn" style="display:inline-flex;align-items:center;text-decoration:none"
        href="{{ \App\Filament\Resources\Courses\CourseResource::getUrl('document', ['record' => $item->course_id, 'document' => $lessonModel->curriculumDocuments->first()->id]) }}">{{ __('media.review_text') }}</a>
@endif
