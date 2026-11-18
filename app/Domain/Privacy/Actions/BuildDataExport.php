<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Actions;

use App\Domain\Privacy\Queries\SubjectData;
use App\Domain\Privacy\RetentionClass;
use App\Domain\Privacy\RetentionPolicies;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Builds the private export archive for one account: one JSON file per topic (including AI conversations), the learner's own
 * submission files and a README/manifest. The archive is stored on the private media disk under a random name and expires after
 * the DataExports retention. Returns where it is; the caller records it on the request. No request or response objects here.
 */
final class BuildDataExport
{
    public function __construct(private readonly SubjectData $data, private readonly RetentionPolicies $policies) {}

    /** @return array{path: string, sha256: string, size: int, expires_at: Carbon, counts: array<string, int>} */
    public function __invoke(User $subject): array
    {
        $userId = (int) $subject->getKey();
        $sections = $this->data->for($userId);
        $counts = array_map('count', $sections);

        $temp = tempnam(sys_get_temp_dir(), 'yg-export-');
        if ($temp === false) {
            throw new RuntimeException('Unable to create a temporary export file.');
        }

        $disk = Storage::disk(Config::string('media.disk'));
        $directory = trim(Config::string('privacy.export_directory'), '/');
        $path = $directory.'/'.Str::uuid()->toString().'.zip';

        try {
            $zip = new ZipArchive;
            if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the export archive.');
            }

            foreach ($sections as $name => $rows) {
                $zip->addFromString($name.'.json', (string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
            }

            $included = 0;
            foreach ($this->data->submissionFiles($userId) as $id => $file) {
                $stream = $disk->exists($file['path']) ? $disk->readStream($file['path']) : null;
                if ($stream === null) {
                    continue;
                }
                $contents = stream_get_contents($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($contents === false) {
                    continue;
                }
                $zip->addFromString('submission_files/'.$id.'-'.$this->safeName($file['name']), $contents);
                $included++;
            }

            $zip->addFromString('manifest.json', (string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'account_id' => $userId,
                'counts' => $counts,
                'submission_files_included' => $included,
                'withheld' => SubjectData::withheld(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->addFromString('README.txt', __('privacy.export_readme', [], 'en')."\n\n".__('privacy.export_readme', [], 'ar')."\n");

            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish the export archive.');
            }

            $stream = fopen($temp, 'rb');
            if ($stream === false || ! $disk->put($path, $stream)) {
                throw new RuntimeException('Unable to store the export archive.');
            }
            if (is_resource($stream)) {
                fclose($stream);
            }

            $hash = hash_file('sha256', $temp);
            $size = filesize($temp);
            if ($hash === false || $size === false) {
                throw new RuntimeException('Unable to measure the export archive.');
            }
        } finally {
            @unlink($temp);
        }

        $days = max(1, $this->policies->days(RetentionClass::DataExports) ?? (int) RetentionClass::DataExports->defaultDays());

        return ['path' => $path, 'sha256' => $hash, 'size' => $size, 'expires_at' => now()->addDays($days), 'counts' => $counts];
    }

    private function safeName(string $name): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $name);

        return mb_substr(trim((string) $clean, '._') ?: 'file', 0, 120);
    }
}
