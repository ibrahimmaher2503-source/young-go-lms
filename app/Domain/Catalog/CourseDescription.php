<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class CourseDescription
{
    public static function clean(string $description): string
    {
        $config = (new HtmlSanitizerConfig)->allowElement('a', ['href'])->allowRelativeLinks();
        foreach (['p', 'br', 'strong', 'em', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote'] as $tag) {
            $config = $config->allowElement($tag);
        }

        return (new HtmlSanitizer($config))->sanitize($description);
    }
}
