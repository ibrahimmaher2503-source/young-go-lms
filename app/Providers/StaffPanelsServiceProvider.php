<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Resources\Audit\AuditLogResource;
use App\Http\Middleware\EnsureActiveAccount;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\ServiceProvider;

final class StaffPanelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (['admin', 'instructor', 'finance'] as $id) {
            Filament::registerPanel(static fn (): Panel => Panel::make()
                ->id($id)
                ->path($id)
                ->default($id === 'admin')
                ->login()
                ->authGuard('web')
                ->brandName(static fn (): string => (string) __('app.name'))
                ->viteTheme('resources/css/filament-theme.css')
                ->renderHook(PanelsRenderHook::TOPBAR_END, static fn () => view('filament.staff.locale-switcher', ['panel' => $id]))
                ->pages([Dashboard::class])
                ->resources($id === 'admin' ? [AuditLogResource::class] : [])
                ->middleware(['web', DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
                ->authMiddleware([Authenticate::class, EnsureActiveAccount::class, AuthenticateSession::class], isPersistent: true));
        }
    }
}
