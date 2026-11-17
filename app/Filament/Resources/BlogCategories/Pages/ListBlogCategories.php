<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Domain\Content\Actions\SaveBlogCategory;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\BlogCategory;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;

final class ListBlogCategories extends WorkspaceListRecords
{
    protected static string $resource = BlogCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->createAnother(false)->using(static function (array $data): BlogCategory {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 403);

            return app(SaveBlogCategory::class)($actor, null, $data);
        })];
    }
}
