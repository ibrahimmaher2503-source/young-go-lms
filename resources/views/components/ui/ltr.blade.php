{{-- Bidi-isolates an LTR token (order reference, receipt number, email, phone, URL) inside Arabic text. Slot is HTML-escaped. --}}
<bdi dir="ltr" {{ $attributes->class('yg-ltr') }}>{{ $slot }}</bdi>
