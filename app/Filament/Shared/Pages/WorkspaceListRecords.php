<?php

declare(strict_types=1);

namespace App\Filament\Shared\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

abstract class WorkspaceListRecords extends ListRecords
{
    /**
     * @return array<array{label: string, value: int|string, icon?: string, tone?: string}>
     */
    protected function getWorkspaceSummary(): array
    {
        return [];
    }

    /** @return array<Component> */
    protected function getWorkspaceSidebar(): array
    {
        return [];
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);
        $sidebar = $this->getWorkspaceSidebar();
        $components = [];

        if ($summary = $this->getWorkspaceSummary()) {
            $components[] = View::make('filament.shared.summary-strip')
                ->viewData(['items' => $summary]);
        }

        $main = Group::make($schema->getComponents())
            ->columnSpan(['xl' => $sidebar ? 1 : 2])
            ->extraAttributes(['class' => 'yg-workspace-main']);

        $layout = [$main];

        if ($sidebar) {
            $layout[] = Group::make($sidebar)
                ->columnSpan(['xl' => 1])
                ->extraAttributes(['class' => 'yg-workspace-sidebar']);
        }

        $components[] = Grid::make(['default' => 1, 'xl' => 2])->extraAttributes(['class' => 'yg-workspace-layout'])->schema($layout);

        return $schema->extraAttributes(['class' => 'yg-workspace-content'])->components($components);
    }

    /** @return array<string, string> */
    #[Unexposed]
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return array<string> */
    #[Unexposed]
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'yg-workspace-page'];
    }
}
