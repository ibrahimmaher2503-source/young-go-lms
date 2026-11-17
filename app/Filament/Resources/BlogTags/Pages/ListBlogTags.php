<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlogTags\Pages;

use App\Domain\Content\Actions\SaveBlogTag;
use App\Filament\Resources\BlogTags\BlogTagResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\BlogTag;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;

final class ListBlogTags extends WorkspaceListRecords
{
    protected static string $resource = BlogTagResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->createAnother(false)->using(static function (array $data): BlogTag {
            $actor = Auth::user();
            abort_unless($actor instanceof User, 403);

            return app(SaveBlogTag::class)($actor, null, $data);
        })];
    }
}
