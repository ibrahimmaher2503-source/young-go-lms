<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Domain\Commerce\CoursePrice;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\CurriculumItem;
use Illuminate\Validation\ValidationException;

/**
 * The Finish step checklist (PRD 9.2 step 7). `submit` items block sending a course to review; `publish` items must also
 * hold before it can go live (pricing is set by the academy, so an instructor's submission is not blocked by it).
 * Advisory items (cover, SEO) never block.
 */
final class CourseReadiness
{
    public const string SUBMIT = 'submit';

    public const string PUBLISH = 'publish';

    public const string ADVISORY = 'advisory';

    /** @return list<array{key: string, ok: bool, level: string, step: int}> */
    public static function checks(Course $course): array
    {
        $basic = ($course->getTranslation('title', 'ar', false) !== '' || $course->getTranslation('title', 'en', false) !== '')
            && $course->category_id !== null && filled($course->level) && filled($course->content_language) && filled($course->format);

        $lessons = CurriculumItem::query()->where('course_id', $course->getKey())->where('itemable_type', 'lesson')->exists();

        $priced = $course->access_mode !== null && (
            ! in_array($course->access_mode, Course::INDIVIDUAL_ACCESS_MODES, true)
            || CoursePrice::query()->where('course_id', $course->getKey())->whereIn('market', self::activeMarkets())->where('amount_minor', '>', 0)->exists()
        );

        $seo = $course->getTranslation('seo_title', 'ar', false) !== '' || $course->getTranslation('seo_title', 'en', false) !== ''
            || $course->getTranslation('seo_description', 'ar', false) !== '' || $course->getTranslation('seo_description', 'en', false) !== '';

        return [
            ['key' => 'basic', 'ok' => $basic, 'level' => self::SUBMIT, 'step' => 1],
            ['key' => 'curriculum', 'ok' => $lessons, 'level' => self::SUBMIT, 'step' => 0],
            ['key' => 'custom', 'ok' => CourseCustomValues::missingRequired($course->custom_values) === [], 'level' => self::SUBMIT, 'step' => 6],
            ['key' => 'pricing', 'ok' => $priced, 'level' => self::PUBLISH, 'step' => 3],
            ['key' => 'cover', 'ok' => filled($course->cover_image), 'level' => self::ADVISORY, 'step' => 4],
            ['key' => 'seo', 'ok' => $seo, 'level' => self::ADVISORY, 'step' => 5],
        ];
    }

    /**
     * Check keys that stop the given action.
     *
     * @param  self::SUBMIT|self::PUBLISH  $for
     * @return list<string>
     */
    public static function blockers(Course $course, string $for): array
    {
        $levels = $for === self::PUBLISH ? [self::SUBMIT, self::PUBLISH] : [self::SUBMIT];

        return array_values(array_map(
            static fn (array $check): string => $check['key'],
            array_filter(self::checks($course), static fn (array $check): bool => ! $check['ok'] && in_array($check['level'], $levels, true)),
        ));
    }

    /**
     * @param  self::SUBMIT|self::PUBLISH  $for
     *
     * @throws ValidationException
     */
    public static function assertReady(Course $course, string $for): void
    {
        $blockers = self::blockers($course, $for);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['status' => __('course_wizard.review.blocked', [
                'items' => implode(', ', array_map(static fn (string $key): string => (string) __('course_wizard.check.'.$key), $blockers)),
            ])]);
        }
    }

    /** @return list<string> */
    private static function activeMarkets(): array
    {
        return array_values(array_map(static fn (Market $m): string => $m->value, array_filter(Market::cases(), static fn (Market $m): bool => $m->isActive())));
    }
}
