<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion\Contracts;

interface PdfTextExtractor
{
    /**
     * The embedded text layer, one entry per page in order (index 0 is page 1). A page with no text layer is "".
     *
     * @return list<string>
     */
    public function extract(string $absolutePath): array;
}
