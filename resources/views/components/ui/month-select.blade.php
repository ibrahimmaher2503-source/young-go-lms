{{-- Props: selected. Always starts empty so a month is never chosen by accident. Month names follow the current locale; values are 1-12. Native name/id/required are forwarded. --}}
@props(['optional' => false, 'selected' => null])
<x-ui.select {{ $attributes }}>
    <option value=""></option>
    @foreach(range(1, 12) as $month)
        <option value="{{ $month }}" @selected((int) $selected === $month)>{{ $month }} — {{ \Illuminate\Support\Carbon::create(2000, $month, 1)->locale(app()->getLocale())->translatedFormat('F') }}</option>
    @endforeach
</x-ui.select>
