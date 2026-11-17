<?php

declare(strict_types=1);

namespace App\Domain\AI\Dto;

final readonly class OcrResult
{
    public function __construct(public string $text, public float $confidence) {}
}
