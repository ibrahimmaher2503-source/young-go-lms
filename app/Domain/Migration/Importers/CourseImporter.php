<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\CourseDescription;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Models\Course;

final class CourseImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Courses;
    }

    public function sourceTable(): string
    {
        return 'course';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'title' => $row['title'] ?? null,
            'short_description' => $row['short_description'] ?? null,
            'description' => $row['description'] ?? null,
            'outcomes' => $row['outcomes'] ?? null,
            'faqs' => $row['faqs'] ?? null,
            'requirements' => $row['requirements'] ?? null,
            'language' => $row['language'] ?? null,
            'category_id' => $row['category_id'] ?? null,
            'sub_category_id' => $row['sub_category_id'] ?? null,
            'price' => $row['price'] ?? null,
            'discount_flag' => $row['discount_flag'] ?? 0,
            'discounted_price' => $row['discounted_price'] ?? null,
            'is_free_course' => $row['is_free_course'] ?? 0,
            'level' => $row['level'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'is_admin' => $row['is_admin'] ?? null,
            'status' => $row['status'] ?? null,
            'is_top_course' => $row['is_top_course'] ?? 0,
            'enable_drip_content' => $row['enable_drip_content'] ?? 0,
            'expiry_period' => $row['expiry_period'] ?? null,
            'course_type' => $row['course_type'] ?? null,
            'thumbnail' => $row['thumbnail'] ?? null,
            'video_url' => $row['video_url'] ?? null,
            'course_overview_provider' => $row['course_overview_provider'] ?? null,
            'meta_keywords' => $row['meta_keywords'] ?? null,
            'meta_description' => $row['meta_description'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $titleText = trim((string) ($row['title'] ?? ''));

        if ($titleText === '') {
            return RowImportResult::rejected('empty_title', 'Legacy course has an empty title.');
        }

        $legacyCategoryId = (int) ($row['sub_category_id'] ?? 0) > 0
            ? (int) $row['sub_category_id']
            : (int) ($row['category_id'] ?? 0);

        $categoryId = $context->mappedId(LegacyEntity::Categories, $legacyCategoryId);
        if ($categoryId === null) {
            return RowImportResult::rejected('missing_category', "Legacy category {$legacyCategoryId} is not mapped.");
        }

        $isFreeFlag = (int) ($row['is_free_course'] ?? 0) === 1;
        $rawPrice = ($row['discount_flag'] ?? 0) == 1 && is_numeric($row['discounted_price'] ?? null)
            ? (float) $row['discounted_price']
            : (float) ($row['price'] ?? 0);

        if (! $isFreeFlag && $rawPrice < 0) {
            return RowImportResult::rejected('invalid_price', 'Legacy course price cannot be negative.');
        }

        $isPaid = ! $isFreeFlag && $rawPrice > 0;
        $amountMinor = $isPaid ? (int) round($rawPrice * 100) : 0;
        $exceptions = [];

        $courseType = strtolower(trim((string) ($row['course_type'] ?? 'general')));
        if ($courseType === 'scorm') {
            $exceptions[] = new ImportExceptionData(
                'scorm_unsupported',
                MigrationExceptionSeverity::DecisionRequired,
                'SCORM course package requires manual review; imported as recorded draft shell.',
            );
        }

        $rawInstructors = array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            explode(',', (string) ($row['user_id'] ?? '')),
        ), static fn (string $part): bool => $part !== '' && $part !== '0'));

        $instructorIds = [];
        foreach ($rawInstructors as $legacyUserId) {
            $mappedUserId = $context->mappedId(LegacyEntity::Users, $legacyUserId);
            if ($mappedUserId !== null) {
                $instructorIds[] = $mappedUserId;
            } else {
                $exceptions[] = new ImportExceptionData(
                    'unmapped_course_instructor',
                    MigrationExceptionSeverity::Warning,
                    "Legacy instructor {$legacyUserId} is not mapped; skipped instructor link.",
                    ['legacy_user_id' => $legacyUserId],
                );
            }
        }

        $ownerType = (int) ($row['is_admin'] ?? 0) === 1 ? Course::OWNER_ACADEMY : Course::OWNER_INDEPENDENT;
        $ownerInstructorId = $instructorIds[0] ?? $context->mappedId(LegacyEntity::Users, $row['creator'] ?? null);
        $status = $this->mapStatus((string) ($row['status'] ?? ''));
        $locale = ScriptNameMapper::detectLocale($titleText, $context->defaultLocale);
        $legacySlug = ScriptNameMapper::slugify($titleText, 'course-'.$sourceId);

        $metadata = [
            'legacy_slug' => $legacySlug,
            'is_paid' => $isPaid,
            'amount_minor' => $amountMinor,
            'status' => $status->value,
            'locale' => $locale,
        ];

        $existingTargetId = $context->existingRecord?->target_id;
        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Courses, $sourceId, Course::class, $existingTargetId, $metadata);

            return $existingTargetId !== null
                ? RowImportResult::updated(Course::class, $shadowId, $metadata, $exceptions)
                : RowImportResult::imported(Course::class, $shadowId, $metadata, $exceptions);
        }

        $course = $existingTargetId !== null ? Course::query()->find($existingTargetId) : null;
        $isNew = $course === null;
        if ($isNew) {
            $course = new Course;
            $course->slug_ar = ScriptNameMapper::uniqueSlug('courses', 'slug_ar', $titleText, 'course-'.$sourceId);
            $course->slug_en = ScriptNameMapper::uniqueSlug('courses', 'slug_en', $titleText, 'course-'.$sourceId);
        }

        $shortDesc = trim(strip_tags((string) ($row['short_description'] ?? '')));
        $rawDescription = trim((string) ($row['description'] ?? ''));
        $cleanDescription = $rawDescription !== '' ? CourseDescription::clean($rawDescription) : '';

        $expiryMonths = is_numeric($row['expiry_period'] ?? null) ? (int) $row['expiry_period'] : 0;

        $course->forceFill([
            'category_id' => $categoryId,
            'owner_type' => $ownerType,
            'owner_instructor_id' => $ownerInstructorId,
            'title' => [$locale => $titleText],
            'short_description' => $shortDesc !== '' ? [ScriptNameMapper::detectLocale($shortDesc, $locale) => $shortDesc] : null,
            'description' => $cleanDescription !== '' ? [ScriptNameMapper::detectLocale($cleanDescription, $locale) => $cleanDescription] : null,
            'faqs' => $this->parseFaqs((string) ($row['faqs'] ?? ''), $locale),
            'requirements' => $this->parseListRows((string) ($row['requirements'] ?? ''), $locale),
            'outcomes' => $this->parseListRows((string) ($row['outcomes'] ?? ''), $locale),
            'level' => $this->mapLevel((string) ($row['level'] ?? '')),
            'content_language' => $this->mapContentLanguage((string) ($row['language'] ?? '')),
            'format' => $courseType === 'live' ? 'live' : 'recorded',
            'status' => $status,
            'access_mode' => $isPaid ? Course::ACCESS_PAID : Course::ACCESS_FREE,
            'sequence_mode' => (int) ($row['enable_drip_content'] ?? 0) === 1 ? Course::SEQUENCE_SEQUENTIAL : Course::SEQUENCE_FREE,
            'featured' => (int) ($row['is_top_course'] ?? 0) === 1,
            'ai_enabled' => false,
            'access_duration_days' => $expiryMonths > 0 ? $expiryMonths * 30 : null,
            'thumbnail_path' => filled($row['thumbnail'] ?? null)
                ? trim((string) $row['thumbnail'])
                : (filled($row['upcoming_image_thumbnail'] ?? null) ? trim((string) $row['upcoming_image_thumbnail']) : null),
            'overview_video_provider' => $this->mapOverviewProvider((string) ($row['course_overview_provider'] ?? '')),
            'overview_video_url' => filled($row['video_url'] ?? null) ? trim((string) $row['video_url']) : null,
            'seo_keywords' => filled($row['meta_keywords'] ?? null) ? ScriptNameMapper::bilingual((string) $row['meta_keywords'], $locale) : null,
            'seo_description' => filled($row['meta_description'] ?? null) ? ScriptNameMapper::bilingual((string) $row['meta_description'], $locale) : null,
        ]);

        if ($isNew && ($createdAt = LegacyDateParser::toUtc($row['date_added'] ?? null)) !== null) {
            $course->created_at = $createdAt;
        }
        $course->save();

        if ($isPaid) {
            CoursePrice::query()->updateOrCreate(
                ['course_id' => $course->getKey(), 'market' => $context->defaultMarket],
                ['currency' => $context->defaultCurrency, 'amount_minor' => $amountMinor],
            );
        }

        if ($instructorIds !== []) {
            $sync = [];
            foreach (array_unique($instructorIds) as $id) {
                $sync[$id] = ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE];
            }
            $course->instructors()->syncWithoutDetaching($sync);
        }

        return $isNew
            ? RowImportResult::imported(Course::class, (int) $course->getKey(), $metadata, $exceptions)
            : RowImportResult::updated(Course::class, (int) $course->getKey(), $metadata, $exceptions);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return Course::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $course = Course::query()->find($targetId);
        if ($course === null) {
            return ['target_missing'];
        }

        $mismatches = [];
        $expectedTitle = trim((string) ($sourceRow['title'] ?? ''));
        $locale = ScriptNameMapper::detectLocale($expectedTitle, 'ar');
        $actualTitle = (string) $course->getTranslation('title', $locale, false);
        if ($actualTitle !== $expectedTitle) {
            $mismatches[] = "title[{$locale}] expected '{$expectedTitle}', got '{$actualTitle}'";
        }

        $expectedStatus = $this->mapStatus((string) ($sourceRow['status'] ?? ''))->value;
        if ($course->status->value !== $expectedStatus) {
            $mismatches[] = "status expected '{$expectedStatus}', got '{$course->status->value}'";
        }

        return $mismatches;
    }

    private function mapStatus(string $raw): CourseStatus
    {
        return match (strtolower(trim($raw))) {
            'active', 'published' => CourseStatus::Published,
            'pending' => CourseStatus::PendingReview,
            'private' => CourseStatus::Private,
            'upcoming' => CourseStatus::Upcoming,
            'archived' => CourseStatus::Archived,
            default => CourseStatus::Draft,
        };
    }

    private function mapLevel(string $raw): string
    {
        $normalized = strtolower(trim($raw));

        return in_array($normalized, SaveCourseWizard::LEVELS, true) ? $normalized : 'all';
    }

    private function mapContentLanguage(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'arabic', 'ar' => 'ar',
            'english', 'en' => 'en',
            'both' => 'both',
            default => 'ar',
        };
    }

    private function mapOverviewProvider(string $raw): ?string
    {
        $normalized = strtolower(trim($raw));

        return match ($normalized) {
            'youtube' => 'youtube',
            'vimeo' => 'vimeo',
            '' => null,
            default => 'external_url',
        };
    }

    /** @return list<array{text: array<string, string>}> */
    private function parseListRows(string $raw, string $fallbackLocale): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === '[]' || $trimmed === 'null') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        $items = is_array($decoded) ? $decoded : (preg_split('/\r\n|\r|\n/', $trimmed) ?: []);
        $rows = [];
        foreach ($items as $item) {
            if (! is_string($item) || trim($item) === '') {
                continue;
            }
            $text = trim($item);
            $rows[] = ['text' => [ScriptNameMapper::detectLocale($text, $fallbackLocale) => $text]];
        }

        return $rows;
    }

    /** @return list<array{question: array<string, string>, answer: array<string, string>}> */
    private function parseFaqs(string $raw, string $fallbackLocale): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === '[]' || $trimmed === '{}' || $trimmed === 'null') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value) && trim($key) !== '' && trim($value) !== '') {
                $q = trim($key);
                $a = trim($value);
                $rows[] = [
                    'question' => [ScriptNameMapper::detectLocale($q, $fallbackLocale) => $q],
                    'answer' => [ScriptNameMapper::detectLocale($a, $fallbackLocale) => $a],
                ];
            } elseif (is_array($value)) {
                $q = trim((string) ($value['faq_question'] ?? ($value['question'] ?? '')));
                $a = trim((string) ($value['faq_answer'] ?? ($value['answer'] ?? '')));
                if ($q !== '' || $a !== '') {
                    $rows[] = [
                        'question' => $q !== '' ? [ScriptNameMapper::detectLocale($q, $fallbackLocale) => $q] : [],
                        'answer' => $a !== '' ? [ScriptNameMapper::detectLocale($a, $fallbackLocale) => $a] : [],
                    ];
                }
            }
        }

        return $rows;
    }
}
