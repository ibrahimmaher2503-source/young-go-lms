<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class CheckTranslations extends Command
{
    protected $signature = 'app:check-translations';

    protected $description = 'Verify Arabic and English translation key parity';

    public function handle(): int
    {
        try {
            $arabic = $this->keys('ar');
            $english = $this->keys('en');
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $missingEnglish = array_diff($arabic, $english);
        $missingArabic = array_diff($english, $arabic);
        foreach (['en' => $missingEnglish, 'ar' => $missingArabic] as $locale => $keys) {
            foreach ($keys as $key) {
                $this->error("Missing {$locale} translation: {$key}");
            }
        }

        if ($missingEnglish !== [] || $missingArabic !== []) {
            return self::FAILURE;
        }

        $this->info('Translation key check passed.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function keys(string $locale): array
    {
        $directory = lang_path($locale);
        if (! is_dir($directory)) {
            throw new RuntimeException("Locale directory missing: {$directory}");
        }

        $keys = [];
        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $translations = (static fn (string $path): mixed => require $path)($file->getPathname());
            if (! is_array($translations)) {
                throw new RuntimeException('Translation file must return an array: '.$file->getPathname());
            }

            $prefix = str_replace(['/', '\\'], '.', substr($file->getRelativePathname(), 0, -4));
            foreach (array_keys(Arr::dot($translations)) as $key) {
                $keys[] = $prefix.'.'.$key;
            }
        }

        $jsonPath = lang_path($locale.'.json');
        if (is_file($jsonPath)) {
            $translations = json_decode(File::get($jsonPath), false, 512, JSON_THROW_ON_ERROR);
            if (! $translations instanceof \stdClass) {
                throw new RuntimeException("JSON translations must be an object: {$jsonPath}");
            }
            foreach (array_keys(get_object_vars($translations)) as $key) {
                $keys[] = 'json:'.$key;
            }
        }

        return $keys;
    }
}
