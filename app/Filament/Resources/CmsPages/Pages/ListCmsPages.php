<?php

declare(strict_types=1);

namespace App\Filament\Resources\CmsPages\Pages;

use App\Domain\Content\Actions\SaveCmsContent;
use App\Filament\Resources\CmsPages\CmsPageResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\CmsPage;
use Filament\Actions\CreateAction;

final class ListCmsPages extends WorkspaceListRecords
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->createAnother(false)
            ->using(static fn (array $data): CmsPage => app(SaveCmsContent::class)(new CmsPage, $data))];
    }
}
