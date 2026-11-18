<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionRules\Pages;

use App\Filament\Resources\CommissionRules\CommissionRuleForm;
use App\Filament\Resources\CommissionRules\CommissionRuleResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

final class ListCommissionRules extends WorkspaceListRecords
{
    protected static string $resource = CommissionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommissionRuleForm::configure(Action::make('create')->label(__('ledger.add_rule'))->icon('heroicon-o-plus')
                ->visible(static fn (): bool => Gate::allows('settlement.manage'))),
        ];
    }
}
