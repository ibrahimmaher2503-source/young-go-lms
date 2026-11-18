<?php

declare(strict_types=1);

namespace App\Filament\Shared\Forms\LessonBlocks;

use App\Domain\Library\LibraryAccess;
use App\Models\LibraryItem;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

final class LibraryImageBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'lesson-library-image';
    }

    public static function getLabel(): string
    {
        return __('content.blocks.image');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            Select::make('library_item_id')->label(__('content.blocks.image'))->required()->searchable()->native(false)
                ->options(static function (): array {
                    $actor = Auth::user();

                    return $actor instanceof User
                        ? LibraryAccess::visibleTo($actor)->where('kind', 'image')->get()->mapWithKeys(
                            static fn (LibraryItem $item): array => [$item->getKey() => (string) $item->title],
                        )->all()
                        : [];
                }),
            TextInput::make('alt')->label(__('content.blocks.alt'))->maxLength(200),
        ]);
    }

    public static function toPreviewHtml(array $config): ?string
    {
        return is_string($config['alt'] ?? null) ? '<p dir="auto">'.e($config['alt']).'</p>' : null;
    }
}
