<?php

declare(strict_types=1);

namespace App\Filament\Shared\Forms\LessonBlocks;

use App\Domain\Content\RichContent;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;

final class EmbedBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'lesson-embed';
    }

    public static function getLabel(): string
    {
        return __('content.blocks.embed');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            TextInput::make('url')->label(__('content.blocks.url'))->required()->url()->maxLength(2000)
                ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || RichContent::embedUrl($value) === null) {
                        $fail(__('content.embed_unavailable'));
                    }
                }),
        ]);
    }

    public static function toPreviewHtml(array $config): ?string
    {
        return is_string($config['url'] ?? null) ? '<p dir="auto">'.e($config['url']).'</p>' : null;
    }
}
