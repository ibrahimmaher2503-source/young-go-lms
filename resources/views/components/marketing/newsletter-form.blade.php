{{-- Props: source (where the form is shown: page, contact, footer, home), idPrefix (unique ids when shown twice). Reusable by the public footer (T-P2-03). --}}
@props(['source' => 'page', 'idPrefix' => 'newsletter'])
<x-ui.ajax-form :action="route('newsletter.store')" :message="__('contact.newsletter.done')" reset>
    <x-marketing.honeypot />
    <input type="hidden" name="source" value="{{ $source }}">
    <x-ui.form-field :for="$idPrefix.'-email'" :label="__('contact.fields.email')" data-field>
        <x-ui.input :id="$idPrefix.'-email'" name="email" type="email" autocomplete="email" required />
    </x-ui.form-field>
    <div data-field class="space-y-1">
        <x-ui.checkbox :id="$idPrefix.'-consent'" name="consent" value="1" :label="__('contact.newsletter.consent')" />
        <p class="text-sm text-muted">{{ __('contact.newsletter.consent_note') }}</p>
    </div>
    <x-ui.button type="submit">{{ __('contact.newsletter.submit') }}</x-ui.button>
</x-ui.ajax-form>
