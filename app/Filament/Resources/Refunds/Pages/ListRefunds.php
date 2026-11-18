<?php

declare(strict_types=1);

namespace App\Filament\Resources\Refunds\Pages;

use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\Queries\RefundQueue;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\User;
use Brick\Math\Exception\MathException;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ListRefunds extends WorkspaceListRecords
{
    protected static string $resource = RefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('request')->label(__('refunds.request'))->icon('heroicon-o-plus')
                ->visible(static fn (): bool => Gate::allows('payment.refund'))
                ->modalHeading(__('refunds.request_heading'))
                ->schema([
                    TextInput::make('order_reference')->label(__('refunds.order_reference'))->required()->maxLength(64),
                    TextInput::make('amount')->label(__('refunds.amount_major'))->required()->regex('/^\d{1,9}(\.\d{1,2})?$/')->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal']),
                    Textarea::make('reason')->label(__('refunds.reason'))->required()->minLength(10)->maxLength(1000)->rows(3),
                ])
                ->action(static function (array $data): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 403);

                    try {
                        $order = Order::query()->where('reference', trim((string) $data['order_reference']))->first();
                        if ($order === null) {
                            throw ValidationException::withMessages(['order_reference' => __('refunds.order_unknown')]);
                        }
                        try {
                            $minor = BrickMoney::of((string) $data['amount'], $order->currency)->getMinorAmount()->toInt();
                        } catch (MathException) {
                            throw ValidationException::withMessages(['amount' => __('refunds.amount_invalid')]);
                        }
                        app(RequestRefund::class)($actor, $order, $minor, (string) $data['reason']);
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

                        throw new Halt;
                    }
                    Notification::make()->success()->title(__('refunds.requested_ok'))->send();
                }),
        ];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $summary = RefundQueue::summary();
        $returned = collect($summary['executed_minor'])->map(static fn (int $minor, string $currency): string => RefundResource::money($minor, $currency))->implode(' / ');

        return [
            ['label' => __('refunds.summary_requested'), 'value' => $summary['requested'], 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('refunds.summary_approved'), 'value' => $summary['approved'], 'icon' => 'heroicon-o-check-circle', 'tone' => 'warning'],
            ['label' => __('refunds.summary_returned'), 'value' => $returned === '' ? '0' : $returned, 'icon' => 'heroicon-o-arrow-uturn-left'],
        ];
    }
}
