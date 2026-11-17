<x-layouts.base variant="public" :title="__('contact.title')">
    <div class="mx-auto max-w-2xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-bold">{{ __('contact.title') }}</h1>
            <p class="text-muted">{{ __('contact.intro') }}</p>
        </header>
        <x-ui.card class="relative" data-contact-form>
            <x-ui.ajax-form :action="route('contact.store')" :message="__('contact.sent')" reset>
                <x-marketing.honeypot />
                <x-ui.form-field for="contact-name" :label="__('contact.fields.name')" data-field>
                    <x-ui.input id="contact-name" name="name" autocomplete="name" maxlength="120" required />
                </x-ui.form-field>
                <x-ui.form-field for="contact-email" :label="__('contact.fields.email')" data-field>
                    <x-ui.input id="contact-email" name="email" type="email" autocomplete="email" maxlength="255" required />
                </x-ui.form-field>
                <x-ui.form-field for="contact-phone" :label="__('contact.fields.phone_optional')" :help="__('contact.phone_help')" data-field>
                    <x-ui.input id="contact-phone" name="phone" type="tel" autocomplete="tel" maxlength="32" aria-describedby="contact-phone-help" />
                </x-ui.form-field>
                <x-ui.form-field for="contact-message" :label="__('contact.fields.message')" data-field>
                    <x-ui.textarea id="contact-message" name="message" rows="6" minlength="10" maxlength="3000" required />
                </x-ui.form-field>
                <p class="text-sm text-muted">{{ __('contact.privacy_note') }} <x-ui.link :href="route('cms.show', ['slug' => 'privacy'])">{{ __('contact.privacy_link') }}</x-ui.link></p>
                <x-ui.button type="submit">{{ __('contact.send') }}</x-ui.button>
            </x-ui.ajax-form>
        </x-ui.card>
        <x-ui.card :title="__('contact.newsletter.title')" class="relative" data-newsletter-card>
            <p class="mb-4 text-muted">{{ __('contact.newsletter.intro') }}</p>
            <x-marketing.newsletter-form source="contact" id-prefix="contact-newsletter" />
        </x-ui.card>
    </div>
</x-layouts.base>
