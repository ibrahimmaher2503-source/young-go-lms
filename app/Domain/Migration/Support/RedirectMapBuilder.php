<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Seo\PublicUrl;
use App\Models\Category;
use App\Models\Course;
use App\Models\InstructorProfile;
use Illuminate\Support\Facades\Config;

/**
 * Builds deterministic, relative same-site 301 redirect pairs from mapped legacy records and known legacy paths.
 */
final class RedirectMapBuilder
{
    /**
     * @return list<array{source_id: string, from_path: string, to_path: string, status_code: int}>
     */
    public function build(LegacySourceReader $reader, LegacyIdMap $idMap, string $sourceSystem): array
    {
        $locale = Config::string('legacy_migration.default_locale', PublicUrl::defaultLocale());
        $entries = [];

        $staticRoutes = [
            'static:courses' => ['/home/courses', '/'.$locale.'/courses'],
            'static:blogs' => ['/blogs', '/'.$locale.'/blog'],
            'static:contact' => ['/home/contact_us', '/contact'],
            'static:about' => ['/home/about_us', '/'.$locale.'/pages/about'],
            'static:privacy' => ['/home/privacy_policy', '/'.$locale.'/pages/privacy'],
            'static:terms' => ['/home/terms_and_condition', '/'.$locale.'/pages/terms'],
        ];

        foreach ($staticRoutes as $sourceId => [$from, $to]) {
            $entries[] = ['source_id' => $sourceId, 'from_path' => $from, 'to_path' => $to, 'status_code' => 301];
        }

        foreach ($idMap->mappedRecords($sourceSystem, LegacyEntity::Categories) as $record) {
            $category = Category::query()->find($record->target_id);
            $legacySlug = is_array($record->metadata) ? ($record->metadata['legacy_slug'] ?? null) : null;
            if ($category === null || ! is_string($legacySlug) || trim($legacySlug) === '') {
                continue;
            }

            $newPath = $this->relativePath(PublicUrl::category($category, $locale));
            $entries[] = [
                'source_id' => 'category:'.$record->source_id,
                'from_path' => '/category/'.ltrim(trim($legacySlug), '/'),
                'to_path' => $newPath,
                'status_code' => 301,
            ];
        }

        foreach ($idMap->mappedRecords($sourceSystem, LegacyEntity::Courses) as $record) {
            $course = Course::query()->find($record->target_id);
            if ($course === null) {
                continue;
            }

            $newPath = $this->relativePath(PublicUrl::course($course, $locale));
            $legacySlug = is_array($record->metadata) ? ($record->metadata['legacy_slug'] ?? null) : null;

            if (is_string($legacySlug) && trim($legacySlug) !== '') {
                $entries[] = [
                    'source_id' => 'course_slug:'.$record->source_id,
                    'from_path' => '/home/course/'.ltrim(trim($legacySlug), '/').'/'.$record->source_id,
                    'to_path' => $newPath,
                    'status_code' => 301,
                ];
            }

            $entries[] = [
                'source_id' => 'course_id:'.$record->source_id,
                'from_path' => '/home/course/'.$record->source_id,
                'to_path' => $newPath,
                'status_code' => 301,
            ];
        }

        foreach ($idMap->mappedRecords($sourceSystem, LegacyEntity::Users) as $record) {
            $profileId = is_array($record->metadata) ? ($record->metadata['instructor_profile_id'] ?? null) : null;
            if (! is_numeric($profileId)) {
                continue;
            }

            $profile = InstructorProfile::query()->find((int) $profileId);
            if ($profile === null) {
                continue;
            }

            $entries[] = [
                'source_id' => 'instructor:'.$record->source_id,
                'from_path' => '/home/instructor_page/'.$record->source_id,
                'to_path' => $this->relativePath(PublicUrl::instructor($profile, $locale)),
                'status_code' => 301,
            ];
        }

        foreach ($idMap->mappedRecords($sourceSystem, LegacyEntity::Certificates) as $record) {
            $meta = is_array($record->metadata) ? $record->metadata : [];
            $shareable = isset($meta['shareable_url']) && is_string($meta['shareable_url']) ? trim($meta['shareable_url']) : '';
            $token = isset($meta['token']) && is_string($meta['token']) ? trim($meta['token']) : '';
            if ($shareable === '' || $token === '') {
                continue;
            }

            $oldSegment = str_contains($shareable, '/') ? basename((string) parse_url($shareable, PHP_URL_PATH)) : $shareable;
            if ($oldSegment === '') {
                continue;
            }

            $entries[] = [
                'source_id' => 'certificate:'.$record->source_id,
                'from_path' => '/certificate/'.ltrim($oldSegment, '/'),
                'to_path' => '/verify/'.$token,
                'status_code' => 301,
            ];
        }

        return $entries;
    }

    private function relativePath(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return '/'.ltrim($path, '/');
    }
}
