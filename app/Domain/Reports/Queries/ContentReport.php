<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Reports\QueryTools;
use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportLabels;
use App\Models\MediaAsset;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class ContentReport implements ReportDefinition
{
    private const array TIERS = [
        'tier_protected' => MediaAsset::TIER_PROTECTED,
        'tier_restricted' => MediaAsset::TIER_RESTRICTED,
        'tier_open' => MediaAsset::TIER_OPEN,
    ];

    public function key(): ReportKey
    {
        return ReportKey::Content;
    }

    public function columns(): array
    {
        return [
            'course' => 'text', 'status' => 'text', 'sections' => 'int', 'lessons' => 'int',
            'missing_thumbnails' => 'int', 'missing_pdfs' => 'int', 'missing_media' => 'int', 'media_exceptions' => 'int',
        ] + array_fill_keys(array_keys(self::TIERS), 'int');
    }

    public function rows(ReportFilters $filters): iterable
    {
        $lessons = DB::table('curriculum_items as items')
            ->join('lessons', 'lessons.id', '=', 'items.itemable_id')
            ->where('items.itemable_type', 'lesson')->where('items.status', '!=', 'archived')
            ->whereNull('lessons.archived_at')->whereColumn('items.course_id', 'courses.id');
        $pdf = DB::table('lesson_curriculum_documents')->selectRaw('1')
            ->whereColumn('lesson_id', 'lessons.id')->where('path', '<>', '');
        $media = DB::table('media_assets')->selectRaw('1')->whereColumn('lesson_id', 'lessons.id');
        $configured = $media->clone()->where(static fn (Builder $query) => $query
            ->where('path', '<>', '')->orWhere('url', '<>', '')->orWhere('external_id', '<>', ''));
        $sections = DB::table('course_sections')->whereColumn('course_id', 'courses.id')->whereNull('archived_at');
        $courses = $this->courses($filters)->select('courses.id', 'courses.title', 'courses.status', 'courses.thumbnail_path', 'courses.cover_image')
            ->selectSub($sections->selectRaw('count(*)'), 'sections')
            ->selectSub($lessons->clone()->selectRaw('count(*)'), 'lessons')
            ->selectSub($lessons->clone()->selectRaw('count(*)')->whereNotExists($pdf), 'missing_pdfs')
            ->selectSub($lessons->clone()->selectRaw('count(*)')->whereIn('lessons.type', ['video', 'audio'])->whereNotExists($configured), 'missing_media')
            ->selectSub($lessons->clone()->selectRaw('count(*)')->whereExists($media->clone()->whereNotNull('exception_at')), 'media_exceptions');
        foreach (self::TIERS as $column => $tier) {
            $courses->selectSub($lessons->clone()->selectRaw('count(*)')->whereExists($media->clone()->where('protection_tier', $tier)), $column);
        }

        foreach ($courses->lazyById(Config::integer('reports.chunk'), 'courses.id', 'id') as $course) {
            $row = [
                ReportLabels::translated((string) $course->title, '#'.$course->id), ReportLabels::status($course->status),
                (int) $course->sections, (int) $course->lessons, (int) blank($course->thumbnail_path ?? $course->cover_image),
                (int) $course->missing_pdfs, (int) $course->missing_media, (int) $course->media_exceptions,
            ];
            foreach (array_keys(self::TIERS) as $column) {
                $row[] = (int) $course->{$column};
            }

            yield $row;
        }
    }

    public function count(ReportFilters $filters): int
    {
        return $this->courses($filters)->count();
    }

    public function totals(ReportFilters $filters): array
    {
        return QueryTools::sums($this->rows($filters), $this->columns(), array_keys(array_filter($this->columns(), static fn (string $type): bool => $type === 'int')));
    }

    private function courses(ReportFilters $filters): Builder
    {
        return DB::table('courses')->whereBetween('courses.created_at', [$filters->from, $filters->to])
            ->when($filters->courseIds !== null, static fn (Builder $query) => $query->whereIn('courses.id', $filters->courseIds ?? []));
    }
}
