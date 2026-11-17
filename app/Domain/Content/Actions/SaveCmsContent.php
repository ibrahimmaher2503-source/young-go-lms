<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Models\CmsPage;
use App\Models\HomepageBlock;
use Illuminate\Support\Facades\Gate;

/** @internal Used by the validated CMS form actions. */
final class SaveCmsContent
{
    /**
     * @template T of CmsPage|HomepageBlock
     *
     * @param  T  $record
     * @param  array<string, mixed>  $data
     * @return T
     */
    public function __invoke(CmsPage|HomepageBlock $record, array $data): CmsPage|HomepageBlock
    {
        Gate::authorize('content.page.manage');
        $record->fill($data)->save();

        return $record;
    }
}
