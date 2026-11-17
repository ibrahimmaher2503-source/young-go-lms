# Calm Filament table workspace

This is the D-026 cookbook for quickly composing staff record-list pages with the approved calm workspace. The [reference](references/calm-workspace-approved-2026-11-17.png) approves layout and tone; it does not approve the mockup logo, sample names/counts, or additional actions. Brand assets remain O-027.

## Shared page contract

Extend `App\Filament\Shared\Pages\WorkspaceListRecords`. It extends Filament `ListRecords` and keeps `parent::content()` in the page composition.

- `protected function getWorkspaceSummary(): array` returns `array<array{label: string, value: int|string, icon?: string, tone?: string}>`; `label` and `value` are required, and `icon` is optional.
- `protected function getWorkspaceSidebar(): array` returns native `Filament\Schemas\Components\Component` instances, commonly `Section` containing `View`.
- An empty summary omits the strip. An empty sidebar (`[]`) gives the native table the full content width.
- The resource table method `table(Table $table): Table` remains the source for table markup, columns, filters, and actions. The list page configures native tabs with `getTabs()`; Filament retains search, sorting, pagination, and exports. Do not duplicate a table in a schema or Blade view.

## Minimal page

A resource list with no extra summary or sidebar only needs its resource binding:

~~~php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit\Pages;

use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListAuditLogs extends WorkspaceListRecords
{
    protected static string $resource = AuditLogResource::class;
}
~~~

The `AuditLogResource` still controls the native table and its `audit.view` access rule.

## Optional summary and Section/View sidebar

Use the resource query so counts and related records follow the same scope as the table. A user-list page can use this pattern; the current staff-user page uses the same resource-scoped query:

~~~php
protected function getWorkspaceSummary(): array
{
    if (! StaffUserResource::canViewAny()) {
        return [];
    }

    $users = StaffUserResource::getEloquentQuery();

    return [[
        'label' => __('staff.total_users'),
        'value' => (clone $users)->count(),
        'icon' => 'heroicon-o-users',
    ]];
}

protected function getWorkspaceSidebar(): array
{
    if (! StaffUserResource::canViewAny()) {
        return [];
    }

    $users = StaffUserResource::getEloquentQuery();
    $roles = Role::query()
        ->where('guard_name', 'web')
        ->withCount([
            'users' => static fn (Builder $query): Builder => $query
                ->whereIn('users.id', (clone $users)->select('users.id')),
        ])
        ->get()
        ->filter(static fn (Role $role): bool => $role->users_count > 0)
        ->sortByDesc('users_count')
        ->values();

    return [
        Section::make(__('staff.roles_overview'))
            ->schema([
                View::make('filament.staff.role-breakdown')
                    ->viewData(['roles' => $roles, 'total' => (clone $users)->count()]),
            ]),
    ];
}
~~~

This example uses real role and user records from the scoped query; it does not seed display counts. Import `StaffUserResource`, `Section`, `View`, `Builder`, and `Role` in a page that adopts it. The named Blade view must exist, use bilingual language keys, and render an empty state when its scoped collection has no rows. Keep queries and ability checks in the page/resource layer, not in the view.

Only add activity content when its specific ability allows it (for example, `Gate::allows('audit.view')`), and query through that resource’s authorized scope. Hiding a link alone is not an access check.

## Layout and states

The base composes the summary above a native Filament schema grid. At narrow widths, the grid has one column and the sidebar stacks; at XL it shares space with the table. Returning no sidebar components lets the table span the full grid. Let Filament own table loading, errors, empty results, and native controls. Custom sidebar views should show a bilingual empty state and must not create fake pending statuses or metrics.

Use semantic design tokens and logical RTL-safe utilities only. Keep the local table scroll contained so it cannot create horizontal page overflow. Preserve light/dark parity and keyboard access. No per-page layout CSS is needed.


## Whole-surface style gate

Read VISUAL_ACCEPTANCE.md before using this recipe. The two-track native grid uses tokenized 2.22:1 proportions when a sidebar exists; absent sidebar gives a full-width table. Controls and tabs live inside the shared card. Page header actions stay in native getHeaderActions(); for example, the existing users export uses ExportCsvAction there, while row actions stay in the resource table. A summary entry can add tone success/info for intentional icon color. Do not invent activation statuses or render unsupported create buttons to imitate sample content.

New native Page/Section forms and dialogs inherit the global theme, typography and control rules. Still capture and review their real screenshots; inheritance does not waive visual acceptance. The application provides shared WorkspaceDashboard, WorkspaceLogin, brand, local avatars, typeface and responsive state vocabulary. Add page-specific content and abilities, not another layout implementation.
