{{-- Decoy field for bots (config contact.honeypot_field). Hidden from sight and assistive tech, skipped by keyboard. --}}
<div aria-hidden="true" class="absolute h-0 w-0 overflow-hidden opacity-0">
    <label>{{ __('contact.honeypot_label') }}<input type="text" name="{{ config('contact.honeypot_field') }}" value="" tabindex="-1" autocomplete="off"></label>
</div>
