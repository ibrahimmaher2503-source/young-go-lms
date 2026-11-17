<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

use App\Domain\AI\Ingestion\Contracts\PageRenderer;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Renders a page with poppler's pdftoppm. Without it OCR is simply unavailable and the page stays unreadable. */
final class PdftoppmRenderer implements PageRenderer
{
    public function render(string $absolutePath, int $page): ?string
    {
        $binary = (new ExecutableFinder)->find('pdftoppm');
        if ($binary === null) {
            return null;
        }

        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr-'.bin2hex(random_bytes(6));
        $process = new Process([$binary, '-png', '-r', '150', '-f', (string) $page, '-l', (string) $page, '-singlefile', $absolutePath, $prefix], null, null, null, 120);
        $process->run();

        return $process->isSuccessful() && is_file($prefix.'.png') ? $prefix.'.png' : null;
    }
}
