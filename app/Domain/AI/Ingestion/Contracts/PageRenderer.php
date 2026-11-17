<?php

declare(strict_types=1);

namespace App\Domain\AI\Ingestion\Contracts;

interface PageRenderer
{
    /** Renders one page to an image file for OCR and returns its path, or null when rendering is not possible. */
    public function render(string $absolutePath, int $page): ?string;
}
