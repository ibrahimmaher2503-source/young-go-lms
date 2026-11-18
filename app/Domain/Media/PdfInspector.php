<?php

declare(strict_types=1);

namespace App\Domain\Media;

use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Inspect the parsed page tree and object dictionaries, including compressed objects. */
final class PdfInspector
{
    private const int MAX_OUTPUT_BYTES = 16 * 1024 * 1024;

    private const array INVALID = ['valid' => false, 'encrypted' => false, 'active_content' => false, 'pages' => 0];

    /** @return array{valid: bool, encrypted: bool, active_content: bool, pages: int} */
    public static function inspect(string $path): array
    {
        $binary = config('media.qpdf_binary') ?: (new ExecutableFinder)->find('qpdf');
        $path = realpath($path);
        if (! is_string($binary) || $path === false) {
            return self::INVALID;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return self::parse($binary, $path);
        }

        // Windows temporary uploads may be open with delete-on-close sharing that QPDF cannot open directly.
        $copy = tempnam(sys_get_temp_dir(), 'inspect-pdf-');
        if ($copy === false) {
            return self::INVALID;
        }
        try {
            return @copy($path, $copy) ? self::parse($binary, $copy) : self::INVALID;
        } finally {
            unlink($copy);
        }
    }

    /** @return array{valid: bool, encrypted: bool, active_content: bool, pages: int} */
    private static function parse(string $binary, string $path): array
    {
        $process = new Process([
            $binary, '--suppress-recovery', '--json=2', '--json-key=pages',
            '--json-key=qpdf', '--json-key=encrypt', '--json-stream-data=none', $path,
        ], timeout: 10);
        $length = 0;
        $overLimit = false;
        try {
            $process->run(function (string $type, string $output) use ($process, &$length, &$overLimit): void {
                $length += strlen($output);
                if (! $overLimit && $length > self::MAX_OUTPUT_BYTES) {
                    $overLimit = true;
                    $process->stop(0);
                }
            });

            if ($overLimit) {
                return self::INVALID;
            }

            if (! $process->isSuccessful()) {
                $encrypted = new Process([$binary, '--is-encrypted', $path], timeout: 5);
                $encrypted->disableOutput();

                return ['encrypted' => $encrypted->run() === 0] + self::INVALID;
            }
        } catch (RuntimeException) {
            return self::INVALID;
        }

        $parsed = json_decode($process->getOutput(), true);
        if (! is_array($parsed['pages'] ?? null) || $parsed['pages'] === []
            || ! is_array($parsed['qpdf'] ?? null) || ! is_bool($parsed['encrypt']['encrypted'] ?? null)) {
            return self::INVALID;
        }

        return [
            'valid' => true,
            'encrypted' => $parsed['encrypt']['encrypted'] || isset($parsed['qpdf'][1]['trailer']['value']['/Encrypt']),
            'active_content' => self::hasActiveContent($parsed['qpdf']),
            'pages' => count($parsed['pages']),
        ];
    }

    /** @param array<array-key, mixed> $object */
    private static function hasActiveContent(array $object): bool
    {
        foreach ($object as $key => $value) {
            if (in_array($key, ['/AA', '/JS', '/JavaScript', '/XFA', '/EF', '/EmbeddedFiles', '/RichMediaContent', '/RichMediaSettings'], true)
                || ($key === '/OpenAction' && ! (is_array($value) && array_is_list($value)))
                || ($key === '/S' && in_array($value, ['/JavaScript', '/Launch', '/SubmitForm', '/ImportData', '/Rendition', '/Movie', '/Sound', '/GoToE', '/RichMediaExecute'], true))
                || (in_array($key, ['/Type', '/Subtype'], true) && in_array($value, ['/EmbeddedFile', '/FileAttachment', '/RichMedia', '/3D', '/Movie', '/Sound', '/Screen'], true))
                || (is_array($value) && self::hasActiveContent($value))) {
                return true;
            }
        }

        return false;
    }
}
