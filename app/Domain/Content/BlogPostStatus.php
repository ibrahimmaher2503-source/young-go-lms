<?php

declare(strict_types=1);

namespace App\Domain\Content;

/** Authoring state of a blog post. A scheduled post goes public by itself once its publish time has passed. */
enum BlogPostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return __('blog.status_'.$this->value);
    }
}
