{{-- Props: instant (DateTimeInterface), market (Market), locale, timezone and ICU pattern. --}}
@props(['instant', 'market', 'locale' => app()->getLocale(), 'timezone' => null, 'pattern' => 'd MMMM yyyy HH:mm'])
<time datetime="{{ $instant->format(DATE_ATOM) }}" {{ $attributes }}>{{ \App\Domain\Shared\Time\LocalizedDate::format($instant, $market, $locale, $pattern, $timezone) }}</time>
