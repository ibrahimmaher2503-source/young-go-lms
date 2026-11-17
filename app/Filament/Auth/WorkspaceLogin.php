<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login;
use Filament\Support\Components\Attributes\Unexposed;

final class WorkspaceLogin extends Login
{
    protected static string $layout = 'filament.shared.layouts.auth';

    #[Unexposed]
    public function getHeading(): string
    {
        return __('staff.sign_in_title');
    }

    #[Unexposed]
    public function getSubheading(): string
    {
        return __('staff.sign_in_help');
    }
}
