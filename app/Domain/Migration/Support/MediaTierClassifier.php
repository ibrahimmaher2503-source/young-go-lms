<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Config;

/**
 * Maps legacy lesson media fields to target MediaAsset providers and T1/T2/T3 protection tiers (Migration Plan §5A).
 */
final class MediaTierClassifier
{
    public const string STATUS_MIGRATED = 'migrated';

    public const string STATUS_REUSED = 'reused';

    public const string STATUS_REPLACED = 'replaced';

    public const string STATUS_EXCEPTION = 'exception';

    /**
     * @param  array<string, mixed>  $row
     * @return array{
     *     lesson_type: string,
     *     provider: string,
     *     kind: string,
     *     protection_tier: string,
     *     external_id: ?string,
     *     url: ?string,
     *     path: ?string,
     *     media_status: string,
     *     exception_reason: ?string,
     *     metadata: array<string, mixed>,
     *     exceptions: list<ImportExceptionData>,
     * }
     */
    public static function classify(array $row, bool $courseIsPaid): array
    {
        $exceptions = [];
        $legacyLessonType = strtolower(trim((string) ($row['lesson_type'] ?? '')));
        $legacyVideoType = strtolower(trim((string) ($row['video_type'] ?? '')));
        $videoUrl = trim((string) ($row['video_url'] ?? ''));
        $audioUrl = trim((string) ($row['audio_url'] ?? ''));
        $mobileUrl = trim((string) ($row['video_url_for_mobile_application'] ?? ''));
        $attachment = trim((string) ($row['attachment'] ?? ''));
        $attachmentType = strtolower(trim((string) ($row['attachment_type'] ?? '')));
        $cloudVideoId = isset($row['cloud_video_id']) && is_numeric($row['cloud_video_id']) ? (int) $row['cloud_video_id'] : null;

        $demoUrl = Config::string('legacy_migration.demo_mobile_video_url', 'https://www.html5rocks.com/en/tutorials/video/basics/devstories.webm');
        $hasDemoMobilePlaceholder = $mobileUrl !== '' && $mobileUrl === $demoUrl;
        if ($hasDemoMobilePlaceholder) {
            $exceptions[] = new ImportExceptionData(
                'demo_mobile_placeholder',
                MigrationExceptionSeverity::Warning,
                'Excluded Academy LMS bundled mobile demo video placeholder.',
                ['mobile_url' => $mobileUrl],
            );
        }

        $metadata = [
            'legacy_lesson_type' => $legacyLessonType !== '' ? $legacyLessonType : null,
            'legacy_video_type' => $legacyVideoType !== '' ? $legacyVideoType : null,
            'demo_mobile_placeholder_excluded' => $hasDemoMobilePlaceholder,
        ];

        // Determine canonical lesson type, provider, tier, and source pointer.
        if ($legacyLessonType === 'wasabi') {
            $provider = 'external_url';
            $kind = 'video';
            $lessonType = 'video';
            $tier = MediaAsset::TIER_OPEN;
            $externalId = null;
            $url = $videoUrl !== '' ? $videoUrl : null;
            $path = null;
            $metadata['legacy_storage_provider'] = 'wasabi_s3';
            $mediaStatus = self::STATUS_REUSED;
        } elseif ($legacyLessonType === 'text' || $attachmentType === 'text') {
            $provider = 'rich_text';
            $kind = 'text';
            $lessonType = 'text';
            $tier = MediaAsset::TIER_PROTECTED;
            $externalId = null;
            $url = null;
            $path = null;
            $metadata['text'] = $attachment !== '' ? $attachment : trim((string) ($row['summary'] ?? ''));
            $mediaStatus = self::STATUS_MIGRATED;
        } elseif ($legacyLessonType === 'audio' || ($audioUrl !== '' && $videoUrl === '')) {
            $provider = 'audio';
            $kind = 'audio';
            $lessonType = 'audio';
            $tier = MediaAsset::TIER_PROTECTED;
            $externalId = null;
            $url = $audioUrl !== '' ? $audioUrl : null;
            $path = self::extractRelativePath($audioUrl);
            $mediaStatus = self::STATUS_MIGRATED;
        } elseif ($legacyLessonType === 'document' || in_array($attachmentType, ['pdf', 'document', 'file'], true)) {
            $provider = 'document';
            $kind = 'document';
            $lessonType = 'document';
            $tier = MediaAsset::TIER_PROTECTED;
            $externalId = null;
            $url = null;
            $path = $attachment !== '' ? 'legacy/documents/'.$attachment : null;
            $mediaStatus = self::STATUS_MIGRATED;
        } elseif ($legacyLessonType === 'image' || $attachmentType === 'image') {
            $provider = 'image';
            $kind = 'image';
            $lessonType = 'document';
            $tier = MediaAsset::TIER_PROTECTED;
            $externalId = null;
            $url = null;
            $path = $attachment !== '' ? 'legacy/images/'.$attachment : null;
            $mediaStatus = self::STATUS_MIGRATED;
        } elseif ($legacyVideoType === 'youtube' || self::extractYouTubeId($videoUrl) !== null) {
            $provider = 'youtube';
            $kind = 'video';
            $lessonType = 'video';
            $tier = MediaAsset::TIER_OPEN;
            $externalId = self::extractYouTubeId($videoUrl);
            $url = $videoUrl !== '' ? $videoUrl : null;
            $path = null;
            $mediaStatus = self::STATUS_REUSED;
        } elseif ($legacyVideoType === 'vimeo' || self::extractVimeoId($videoUrl) !== null) {
            $provider = 'vimeo';
            $kind = 'video';
            $lessonType = 'video';
            $tier = MediaAsset::TIER_OPEN;
            $externalId = self::extractVimeoId($videoUrl);
            $url = $videoUrl !== '' ? $videoUrl : null;
            $path = null;
            $mediaStatus = self::STATUS_REUSED;
        } elseif ($legacyVideoType === 'academy_cloud' && $cloudVideoId !== null && $cloudVideoId > 0) {
            $provider = 'external_url';
            $kind = 'video';
            $lessonType = 'video';
            $tier = MediaAsset::TIER_OPEN;
            $externalId = (string) $cloudVideoId;
            $url = $videoUrl !== '' ? $videoUrl : null;
            $path = null;
            $metadata['legacy_storage_provider'] = 'academy_cloud';
            $mediaStatus = self::STATUS_EXCEPTION;
            $exceptions[] = new ImportExceptionData(
                'academy_cloud_provider_unknown',
                MigrationExceptionSeverity::DecisionRequired,
                'Academy Cloud video requires O-003 provider verification before playback.',
                ['cloud_video_id' => $cloudVideoId],
            );
        } elseif ($legacyVideoType === 'system' || str_contains($videoUrl, '/uploads/lesson_files/')) {
            $provider = 'signed_storage';
            $kind = 'video';
            $lessonType = 'video';
            $tier = MediaAsset::TIER_PROTECTED;
            $externalId = null;
            $url = null;
            $path = self::extractRelativePath($videoUrl);
            $mediaStatus = self::STATUS_MIGRATED;
        } elseif ($attachmentType === 'iframe' || $legacyLessonType === 'iframe') {
            $provider = 'iframe';
            $kind = 'embed';
            $lessonType = 'video';
            $tier = MediaAsset::TIER_OPEN;
            $externalId = null;
            $url = $attachment !== '' ? $attachment : ($videoUrl !== '' ? $videoUrl : null);
            $path = null;
            $mediaStatus = self::STATUS_REUSED;
        } else {
            $provider = 'external_url';
            $kind = 'video';
            $lessonType = 'video';
            $tier = MediaAsset::TIER_OPEN;
            $externalId = null;
            $url = $videoUrl !== '' ? $videoUrl : null;
            $path = null;
            $metadata['legacy_storage_provider'] = $legacyVideoType !== '' ? $legacyVideoType : 'unknown';
            $mediaStatus = self::STATUS_REUSED;
        }

        $exceptionReason = null;
        if ($courseIsPaid && $tier === MediaAsset::TIER_OPEN) {
            $mediaStatus = self::STATUS_EXCEPTION;
            $exceptionReason = 'legacy_migration_paid_t3_pending_owner_decision';
            $exceptions[] = new ImportExceptionData(
                'paid_t3_media',
                MigrationExceptionSeverity::DecisionRequired,
                'Paid lesson uses T3 open media source; owner must choose to re-host to T1, keep with exception, or make free.',
                ['provider' => $provider, 'legacy_video_type' => $legacyVideoType, 'url' => $url],
            );
        }

        $metadata['media_status'] = $mediaStatus;

        return [
            'lesson_type' => $lessonType,
            'provider' => $provider,
            'kind' => $kind,
            'protection_tier' => $tier,
            'external_id' => $externalId,
            'url' => $url,
            'path' => $path,
            'media_status' => $mediaStatus,
            'exception_reason' => $exceptionReason,
            'metadata' => $metadata,
            'exceptions' => $exceptions,
        ];
    }

    private static function extractRelativePath(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH);
        $raw = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : $url;
        $trimmed = ltrim($raw, '/');

        return $trimmed !== '' ? mb_substr($trimmed, 0, 255) : null;
    }

    private static function extractYouTubeId(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';

        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{11})/?$#', $path, $match) === 1) {
            return $match[1];
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if ($path === '/watch' && preg_match('/(?:^|&)v=([A-Za-z0-9_-]{11})(?:&|$)/', $query, $match) === 1) {
                return $match[1];
            }

            if (preg_match('#^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$#', $path, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    private static function extractVimeoId(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('#^/(?:video/)?(\d{6,12})(?:/[A-Za-z0-9]{8,12})?/?$#', $path, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
