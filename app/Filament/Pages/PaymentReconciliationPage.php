<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Commerce\Actions\ReviewPaymentMismatch;
use App\Domain\Commerce\PaymentReconciliationMismatch;
use App\Domain\Commerce\Queries\ReconciliationReport;
use App\Domain\Commerce\ReconciliationMismatchStatus;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * T-P8-11, YG-GAP-PAY-003: finance reads the daily Paymob reconciliation mismatches (payment.view) and marks them reviewed
 * (payment.approve_manual). Report only: nothing here changes an order or an entitlement.
 */
final class PaymentReconciliationPage extends Page
{
    protected static ?string $slug = 'payment-reconciliation';

    protected string $view = 'filament.commerce.reconciliation';

    /** open | reviewed | all */
    public string $filter = 'open';

    public static function getNavigationLabel(): string
    {
        return __('payment_reconciliation.nav');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-scale';
    }

    public static function canAccess(): bool
    {
        return Auth::user() instanceof User && (Gate::allows('payment.view') || Gate::allows('payment.approve_manual'));
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    #[Unexposed]
    public function getTitle(): string
    {
        return __('payment_reconciliation.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function markReviewed(int $id): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && self::canAccess(), 403);

        try {
            app(ReviewPaymentMismatch::class)($user, PaymentReconciliationMismatch::query()->findOrFail($id));
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('payment_reconciliation.denied'))->send();

            return;
        }

        Notification::make()->success()->title(__('payment_reconciliation.reviewed_done'))->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        $report = app(ReconciliationReport::class);
        $user = Auth::user();

        return [
            'run' => $report->latestRun(),
            'openCount' => $report->openCount(),
            'mismatches' => $report->mismatches(ReconciliationMismatchStatus::tryFrom($this->filter)),
            'canReview' => $user instanceof User && $user->can('payment.approve_manual'),
            'limit' => ReconciliationReport::LIMIT,
        ];
    }
}
