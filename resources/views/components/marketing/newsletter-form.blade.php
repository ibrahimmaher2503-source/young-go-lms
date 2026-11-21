{{-- Props: source (where the form is shown: page, contact, footer, home), idPrefix (unique ids when shown twice). Reusable by the public footer (T-P2-03). --}}
@props(['source' => 'page', 'idPrefix' => 'newsletter', 'compact' => false])
<x-ui.ajax-form :action="$source === 'home' || request()->routeIs('public.*') ? route('public.newsletter.store', ['publicLocale' => app()->getLocale()]) : route('newsletter.store')"
    :message="__('contact.newsletter.done')" :class="$compact ? 'home-newsletter-form' : ''" reset>
    <x-marketing.honeypot />
    <input type="hidden" name="source" value="{{ $source }}">
    @if($compact)
        <div class="home-newsletter-fields">
            <div class="home-newsletter-email" data-field>
                <label for="{{ $idPrefix }}-email" class="sr-only">{{ __('contact.fields.email') }}</label>
                <x-ui.icon name="mail"/>
                <x-ui.input :id="$idPrefix.'-email'" name="email" type="email" autocomplete="email" :placeholder="__('public-site.newsletter_email')" required/>
            </div>
            <button type="submit" class="home-button home-newsletter-submit">{{ __('public-site.newsletter_submit') }}<x-ui.icon name="arrow"/></button>
        </div>
    @else
        <x-ui.form-field :for="$idPrefix.'-email'" :label="__('contact.fields.email')" data-field>
            <x-ui.input :id="$idPrefix.'-email'" name="email" type="email" autocomplete="email" required />
        </x-ui.form-field>
    @endif
    <div data-field class="space-y-1">
        <x-ui.checkbox :id="$idPrefix.'-consent'" name="consent" value="1" :label="__('contact.newsletter.consent')" />
        <p class="text-sm text-muted">{{ __('contact.newsletter.consent_note') }}</p>
    </div>
    @unless($compact)<x-ui.button type="submit">{{ __('contact.newsletter.submit') }}</x-ui.button>@endunless
</x-ui.ajax-form>
