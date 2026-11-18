<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class PdfFixture
{
    public static function bytes(int $pages = 1, string $marker = ''): string
    {
        $pdf = "%PDF-1.4\n% {$marker}\n";
        $kids = implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", range(3, $pages + 2)));
        $objects = [
            '<</Type/Catalog/Pages 2 0 R>>',
            "<</Type/Pages/Kids[{$kids}]/Count {$pages}/MediaBox[0 0 612 792]>>",
            ...array_fill(0, $pages, '<</Type/Page/Parent 2 0 R/Resources<<>>>>'),
        ];
        $offsets = "0000000000 65535 f \n";
        foreach ($objects as $index => $object) {
            $offsets .= sprintf("%010d 00000 n \n", strlen($pdf));
            $id = $index + 1;
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $size = count($objects) + 1;

        return $pdf."xref\n0 {$size}\n{$offsets}trailer\n<</Size {$size}/Root 1 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }

    public static function encryptedBytes(): string
    {
        $input = tempnam(sys_get_temp_dir(), 'pdf-input');
        $output = tempnam(sys_get_temp_dir(), 'pdf-output');
        file_put_contents($input, self::bytes());
        try {
            $binary = config('media.qpdf_binary') ?: (new ExecutableFinder)->find('qpdf');
            (new Process([$binary, '--encrypt', 'user-password', 'owner-password', '256', '--', $input, $output]))->mustRun();

            return (string) file_get_contents($output);
        } finally {
            unlink($input);
            unlink($output);
        }
    }
}
