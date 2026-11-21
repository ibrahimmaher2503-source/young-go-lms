@php
    $locale = app()->getLocale();
    $action = request()->routeIs('public.contact.show') ? route('public.contact.store', ['publicLocale' => $locale]) : route('contact.store');
    $pages = app(\App\Domain\Content\Queries\PublicSitePages::class)();
    $privacy = $pages->get('privacy');
    $faq = $pages->get('faq');
    $settings = app(\App\Domain\Settings\GeneralSettings::class);
    $email = filter_var($settings->contact_email, FILTER_VALIDATE_EMAIL);
    $phone = preg_replace('/[^+0-9]/', '', (string) $settings->contact_phone) ?? '';
    $hasPhone = preg_match('/^\+?\d{5,15}$/D', $phone);
    $channels = $settings->socialLinks();
@endphp
<x-layouts.base variant="public" :title="__('contact.title')" full>
    <article class="info-page" data-info-page="contact" aria-labelledby="info-page-title">
        <x-public.info-hero :title="__('contact.title')" :description="__('contact.intro')" image="contact"/>

        <div class="info-contact-grid">
            <x-ui.card class="info-card" aria-labelledby="contact-form-title" data-contact-form>
                <div class="info-card-row mb-4">
                    <span class="info-icon"><x-ui.icon name="mail"/></span>
                    <div>
                        <h2 id="contact-form-title" class="info-section-title" tabindex="-1">{{ __('public-info.contact.form_title') }}</h2>
                        <p>{{ __('public-info.contact.form_intro') }}</p>
                    </div>
                </div>
                <x-ui.ajax-form :action="$action" :message="__('contact.sent')" reset>
                    <x-marketing.honeypot />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.form-field for="contact-name" :label="__('contact.fields.name')" data-field>
                            <div class="info-input-icon">
                                <x-ui.icon name="user"/>
                                <x-ui.input id="contact-name" name="name" autocomplete="name" maxlength="120" :placeholder="__('public-info.contact.name_placeholder')" required />
                            </div>
                        </x-ui.form-field>
                        <x-ui.form-field for="contact-email" :label="__('contact.fields.email')" data-field>
                            <div class="info-input-icon">
                                <x-ui.icon name="mail"/>
                                <x-ui.input id="contact-email" name="email" type="email" autocomplete="email" maxlength="255" :placeholder="__('public-info.contact.email_placeholder')" required />
                            </div>
                        </x-ui.form-field>
                    </div>
                    <x-ui.form-field for="contact-phone" :label="__('contact.fields.phone_optional')" :help="__('contact.phone_help')" class="sm:max-w-xs" data-field>
                        <x-ui.input id="contact-phone" name="phone" type="tel" autocomplete="tel" maxlength="32" aria-describedby="contact-phone-help" />
                    </x-ui.form-field>
                    <x-ui.form-field for="contact-message" :label="__('contact.fields.message')" data-field>
                        <div class="info-input-icon">
                            <x-ui.icon name="pencil"/>
                            <x-ui.textarea id="contact-message" name="message" rows="3" minlength="10" maxlength="3000" :placeholder="__('public-info.contact.message_placeholder')" required />
                        </div>
                    </x-ui.form-field>
                    <div class="info-contact-submit border-t border-border pt-3">
                        <x-ui.button type="submit" class="w-full rounded-full sm:min-w-48 sm:w-auto"><x-ui.icon name="send"/>{{ __('contact.send') }}</x-ui.button>
                        <p class="text-sm text-muted">
                            {{ __('contact.privacy_note') }}
                            @if($privacy)
                                <x-ui.link :href="\App\Domain\Seo\PublicUrl::page($privacy, $locale)">{{ __('contact.privacy_link') }}</x-ui.link>
                            @endif
                        </p>
                    </div>
                </x-ui.ajax-form>
            </x-ui.card>

            <aside class="info-card info-contact-channels" aria-labelledby="contact-channels-title" data-contact-channels>
                <h2 id="contact-channels-title" class="info-section-title">{{ __('public-info.contact.channels') }}</h2>
                <p>{{ __('public-info.contact.channels_intro') }}</p>
                <ul class="divide-y divide-border">
                    @if($email)
                        <li class="info-card-row">
                            <span class="info-icon"><x-ui.icon name="mail"/></span>
                            <div class="min-w-0">
                                <h3>{{ __('contact.fields.email') }}</h3>
                                <x-ui.link :href="'mailto:'.$email"><bdi>{{ $email }}</bdi></x-ui.link>
                            </div>
                        </li>
                    @endif
                    @if($hasPhone)
                        <li class="info-card-row">
                            <span class="info-icon"><x-ui.icon name="phone"/></span>
                            <div class="min-w-0">
                                <h3>{{ __('public-info.contact.phone') }}</h3>
                                <x-ui.link :href="'tel:'.$phone"><bdi>{{ $settings->contact_phone }}</bdi></x-ui.link>
                            </div>
                        </li>
                    @endif
                    @if(! $email && ! $hasPhone)
                        <li class="info-card-row">
                            <span class="info-icon"><x-ui.icon name="mail"/></span>
                            <x-ui.link href="#contact-form-title">{{ __('public-info.contact.form_title') }}</x-ui.link>
                        </li>
                        @if($faq)
                            <li class="info-card-row">
                                <span class="info-icon"><x-ui.icon name="help"/></span>
                                <x-ui.link :href="\App\Domain\Seo\PublicUrl::page($faq, $locale)">{{ $faq->title }}</x-ui.link>
                            </li>
                        @endif
                    @endif
                </ul>
                @if($channels)
                    <div class="border-t border-border pt-3">
                        <h3>{{ __('public-info.contact.social') }}</h3>
                        <ul class="info-contact-social mt-2">
                            @foreach($channels as $platform => $href)
                                <li><a href="{{ $href }}" aria-label="{{ __('public-site.'.$platform) }}"><x-ui.icon :name="$platform"/></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>

        <section class="info-features" aria-labelledby="contact-reasons-title">
            <h2 id="contact-reasons-title" class="info-section-title">{{ __('public-info.contact.reasons') }}</h2>
            <div class="info-feature-grid">
                @foreach(['star', 'file', 'sparkle', 'help'] as $icon)
                    <div class="info-card">
                        <span class="info-icon"><x-ui.icon :name="$icon"/></span>
                        <h3>{{ __('public-info.contact.topics.'.$icon.'.title') }}</h3>
                        <p>{{ __('public-info.contact.topics.'.$icon.'.body') }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <x-public.info-banner id="contact-newsletter" class="home-newsletter" data-newsletter-card
            :title="__('contact.newsletter.title')" :description="__('contact.newsletter.intro')"
            image="images/public-info/contact-band.webp">
            <div class="mt-3"><x-marketing.newsletter-form source="contact" id-prefix="contact-newsletter" compact/></div>
        </x-public.info-banner>
    </article>
</x-layouts.base>
