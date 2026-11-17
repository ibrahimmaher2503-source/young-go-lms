<?php

declare(strict_types=1);

namespace App\Filament\Resources\HomepageBlocks\Pages;

use App\Domain\Content\Actions\SaveCmsContent;
use App\Filament\Resources\HomepageBlocks\HomepageBlockResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\HomepageBlock;
use Filament\Actions\CreateAction;

final class ListHomepageBlocks extends WorkspaceListRecords
{
    protected static string $resource = HomepageBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->createAnother(false)
            ->using(static fn (array $data): HomepageBlock => app(SaveCmsContent::class)(new HomepageBlock, $data))];
    }
}
