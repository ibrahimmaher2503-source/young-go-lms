<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Pages;

use App\Domain\Commerce\Queries\CouponUsage;
use App\Filament\Resources\Coupons\CouponForm;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

final class ListCoupons extends WorkspaceListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CouponForm::configure(Action::make('create')->label(__('commerce.coupon_create'))->icon('heroicon-o-plus')
                ->visible(static fn (): bool => Gate::allows('payment.approve_manual'))),
        ];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $usage = CouponUsage::for();
        $given = collect($usage['discount_minor'])->map(static fn (int $minor, string $currency): string => BrickMoney::ofMinor($minor, $currency)->formatToLocale('en-US'))->implode(' / ');

        return [
            ['label' => __('commerce.coupon_used'), 'value' => $usage['redeemed'], 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('commerce.coupon_reserved'), 'value' => $usage['reserved'], 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('commerce.coupon_discount_given'), 'value' => $given === '' ? '0' : $given, 'icon' => 'heroicon-o-receipt-percent'],
        ];
    }
}
