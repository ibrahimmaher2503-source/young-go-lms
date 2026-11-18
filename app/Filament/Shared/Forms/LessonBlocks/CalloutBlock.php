<?php

declare(strict_types=1);

namespace App\Filament\Shared\Forms\LessonBlocks;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

final class CalloutBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'lesson-callout';
    }

    public static function getLabel(): string
    {
        return __('content.blocks.callout');
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action->schema([
            Select::make('tone')->label(__('content.blocks.tone'))->options([
                'info' => __('content.blocks.info'),
                'tip' => __('content.blocks.tip'),
                'warning' => __('content.blocks.warning'),
            ])->required()->default('info'),
            Textarea::make('text')->label(__('content.blocks.text'))->required()->maxLength(2000),
        ]);
    }

    public static function toPreviewHtml(array $config): ?string
    {
        return is_string($config['text'] ?? null) ? '<p dir="auto">'.e($config['text']).'</p>' : null;
    }
}
