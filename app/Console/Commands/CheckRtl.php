<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CheckRtl extends Command
{
    protected $signature = 'app:check-rtl';

    protected $description = 'Reject physical direction utilities in application views';

    public function handle(): int
    {
        $failed = false;
        foreach (config('view.paths', []) as $path) {
            if (! is_dir($path)) {
                $this->error("View directory missing: {$path}");
                $failed = true;

                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $pattern = '/(?<![\w-])(?:-?(?:ml|mr|pl|pr|left|right)-[\w\[\].\/%-]+|text-(?:left|right)(?![\w-])|(?:rounded|border)-(?:l|r)(?=-|\b))/';
                if (preg_match_all($pattern, $file->getContents(), $matches)) {
                    $this->error($file->getPathname().': '.implode(', ', array_unique($matches[0])));
                    $failed = true;
                }
            }
        }

        if (! $failed) {
            $this->info('RTL utility check passed.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
