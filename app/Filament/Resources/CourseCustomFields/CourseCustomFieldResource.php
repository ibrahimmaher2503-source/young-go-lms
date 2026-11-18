<?php

declare(strict_types=1);

namespace App\Filament\Resources\CourseCustomFields;

use App\Domain\Catalog\Models\CourseCustomField;
use App\Filament\Resources\CourseCustomFields\Pages\ListCourseCustomFields;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** T-P3-03: admin-defined course fields (admins with `course.publish`). Writes go through SaveCourseCustomField. */
final class CourseCustomFieldResource extends Resource
{
    protected static ?string $model = CourseCustomField::class;

    protected static ?string $slug = 'course-custom-fields';

    public static function getModelLabel(): string
    {
        return __('course_wizard.fields.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('course_wizard.fields.title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('panel.admin.access') && $user->can('course.publish');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')
            ->columns([
                TextColumn::make('label')->label(__('course_wizard.fields.label'))->state(static fn (CourseCustomField $f): string => (string) $f->label),
                TextColumn::make('field_key')->label(__('course_wizard.fields.key'))->fontFamily('mono')->searchable(),
                TextColumn::make('type')->label(__('course_wizard.fields.type'))->formatStateUsing(static fn (string $state): string => __('course_wizard.fields.types.'.$state)),
                TextColumn::make('visibility')->label(__('course_wizard.fields.visibility'))->formatStateUsing(static fn (string $state): string => __('course_wizard.fields.'.$state)),
                StatusBadgeColumn::make('is_active', ['active' => 'success', 'inactive' => 'gray'])->label(__('course_wizard.fields.active'))
                    ->state(static fn (CourseCustomField $f): string => $f->is_active ? 'active' : 'inactive')
                    ->formatStateUsing(static fn (mixed $state): string => __('categories.'.$state)),
            ])
            ->recordActions([
                CourseCustomFieldForm::configure(Action::make('edit')->label(__('course_wizard.fields.edit'))->icon('heroicon-o-pencil-square')->modalHeading(__('course_wizard.fields.edit'))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCourseCustomFields::route('/')];
    }
}
