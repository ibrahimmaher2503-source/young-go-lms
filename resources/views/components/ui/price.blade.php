{{-- Props: money (shared Money), market (Market), locale (active locale by default), negative (show as a deduction, e.g. a discount: the sign and the amount are one LTR-isolated run, so the sign never lands on the wrong side in Arabic). --}}
@props(['money', 'market', 'locale' => app()->getLocale(), 'negative' => false])
@php($formatted = app(\App\Domain\Shared\Money\PriceFormatter::class)->format($money, $market, $locale))
@if($negative)
<bdi dir="ltr" {{ $attributes->class('yg-ltr') }}>&minus;{{ $formatted }}</bdi>
@else
<bdi dir="auto" {{ $attributes }}>{{ $formatted }}</bdi>
@endif
