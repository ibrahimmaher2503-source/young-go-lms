<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons;

use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\CouponType;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Filament\Shared\Tables\LocalizedDateColumn;
use App\Filament\Shared\Tables\StatusBadgeColumn;
use Brick\Money\Money as BrickMoney;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * T-P8-05: staff list of coupons with the usage report (confirmed and reserved uses, discount given). Finance and admin
 * (payment.approve_manual) manage; payment.view may read. Writes go through SaveCoupon via the shared modal.
 */
final class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $slug = 'coupons';

    public static function getModelLabel(): string
    {
        return __('commerce.coupon');
    }

    public static function getPluralModelLabel(): string
    {
        return __('commerce.coupons_title');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-ticket';
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('payment.view') || Gate::allows('payment.approve_manual');
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

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'redemptions as redeemed_count' => static fn (Builder $q) => $q->where('status', CouponRedemptionStatus::Redeemed->value),
            'redemptions as reserved_count' => static fn (Builder $q) => $q->where('status', CouponRedemptionStatus::Reserved->value),
        ])->withSum(['redemptions as discount_sum' => static fn (Builder $q) => $q->where('status', CouponRedemptionStatus::Redeemed->value)], 'discount_minor');
    }

    public static function state(Coupon $coupon): string
    {
        return match (true) {
            ! $coupon->is_active => 'inactive',
            $coupon->ends_at !== null && $coupon->ends_at->lte(now()) => 'expired',
            $coupon->starts_at !== null && $coupon->starts_at->gt(now()) => 'scheduled',
            $coupon->max_redemptions !== null && $coupon->redemptions_count >= $coupon->max_redemptions => 'exhausted',
            default => 'active',
        };
    }

    public static function table(Table $table): Table
    {
        $colors = ['active' => 'success', 'scheduled' => 'info', 'inactive' => 'gray', 'expired' => 'gray', 'exhausted' => 'warning'];

        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->label(__('commerce.coupon_code'))->searchable()->fontFamily('mono'),
                TextColumn::make('name')->label(__('commerce.coupon_name'))->searchable(),
                TextColumn::make('value')->label(__('commerce.coupon_type'))->state(static fn (Coupon $c): string => $c->discount_type === CouponType::Percent
                    ? $c->percent.'%' : BrickMoney::ofMinor((int) $c->amount_minor, (string) $c->currency)->formatToLocale('en-US')),
                StatusBadgeColumn::make('state', $colors)->label(__('commerce.coupon_status'))->state(static fn (Coupon $c): string => self::state($c))
                    ->formatStateUsing(static fn (mixed $state): string => __('commerce.coupon_state_'.$state)),
                TextColumn::make('uses')->label(__('commerce.coupon_used'))->state(static fn (Coupon $c): string => $c->getAttribute('redeemed_count').' / '.($c->max_redemptions ?? __('commerce.coupon_unlimited'))),
                TextColumn::make('reserved_count')->label(__('commerce.coupon_reserved')),
                TextColumn::make('discount')->label(__('commerce.coupon_discount_given'))->state(static fn (Coupon $c): string => BrickMoney::ofMinor((int) $c->getAttribute('discount_sum'), $c->currency ?? Market::Egypt->currency())->formatToLocale('en-US')),
                LocalizedDateColumn::make('starts_at')->label(__('commerce.coupon_starts'))->placeholder('-'),
                LocalizedDateColumn::make('ends_at')->label(__('commerce.coupon_ends'))->placeholder('-'),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('commerce.coupon_active'))])
            ->recordActions([
                CouponForm::configure(Action::make('edit')->label(__('commerce.coupon_edit'))->icon('heroicon-o-pencil-square')
                    ->visible(static fn (): bool => Gate::allows('payment.approve_manual'))
                    ->modalHeading(__('commerce.coupon_edit'))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCoupons::route('/')];
    }
}
