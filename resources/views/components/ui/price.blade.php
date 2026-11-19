{{-- Props: money (shared Money), market (Market), locale (active locale by default), negative (show as a deduction, e.g. a discount: the sign and the amount are one LTR-isolated run, "-50.00 ج.م." in Arabic, so the sign never lands on the wrong side). Never breaks inside the amount. --}}
@props(['money', 'market', 'locale' => app()->getLocale(), 'negative' => false])
@php($formatter = app(\App\Domain\Shared\Money\PriceFormatter::class))
@if($negative)
<bdi dir="ltr" {{ $attributes->class('yg-ltr whitespace-nowrap') }}>{{ $formatter->formatDeduction($money, $market, $locale) }}</bdi>
@else
<bdi dir="auto" {{ $attributes->class('whitespace-nowrap') }}>{{ $formatter->format($money, $market, $locale) }}</bdi>
@endif
