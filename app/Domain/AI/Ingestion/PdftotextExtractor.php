<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion;

use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Reads the text layer with poppler's pdftotext (pages are separated by form feeds). Fails loudly if it is missing. */
final class PdftotextExtractor implements PdfTextExtractor
{
    public function extract(string $absolutePath): array
    {
        $binary = (new ExecutableFinder)->find('pdftotext');
        if ($binary === null) {
            throw new RuntimeException('pdftotext is not installed on this server.');
        }

        $process = new Process([$binary, '-enc', 'UTF-8', '-layout', $absolutePath, '-'], null, null, null, 120);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('pdftotext failed: '.trim($process->getErrorOutput()));
        }

        $pages = explode("\f", $process->getOutput());
        if (trim($pages[array_key_last($pages)]) === '') {
            array_pop($pages); // the form feed after the last page leaves an empty tail
        }

        return $pages;
    }
}
