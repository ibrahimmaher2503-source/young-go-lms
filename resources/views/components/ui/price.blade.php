{{-- Props: money (shared Money), market (Market), locale (active locale by default). --}}
@props(['money', 'market', 'locale' => app()->getLocale()])
<bdi dir="auto" {{ $attributes }}>{{ app(\App\Domain\Shared\Money\PriceFormatter::class)->format($money, $market, $locale) }}</bdi>
