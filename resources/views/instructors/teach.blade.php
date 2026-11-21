@php
    $field = fn (string $name) => $errors->first($name);
    $describedBy = fn (string $name, bool $hasHelp = false): ?string => trim(($hasHelp ? $name.'-help' : '').($errors->has($name) ? ' '.$name.'-error' : '')) ?: null;
    $privacy = $state === 'form' ? app(\App\Domain\Content\Queries\PublicSitePages::class)()->get('privacy') : null;
@endphp
<x-layouts.base variant="public" :title="__('instructor_onboarding.title')" wide>
    <div @class(['public-page-stack mx-auto', 'max-w-6xl' => $state === 'guest', 'max-w-3xl' => $state !== 'guest']) data-teach-page>
        @if($state === 'guest')
            <section aria-labelledby="teach-heading" data-teach-state="guest" class="grid overflow-hidden rounded-xl border border-border bg-surface shadow-card lg:grid-cols-2">
                <div class="flex flex-col justify-center p-5 sm:p-8">
                    <h1 id="teach-heading" class="text-3xl font-extrabold sm:text-4xl">{{ __('instructor_onboarding.title') }}</h1>
                    <p class="mt-3 max-w-prose text-muted">{{ __('instructor_onboarding.intro') }}</p>
                    <p class="mt-6 border-t border-border pt-4">{{ __('instructor_onboarding.sign_in_needed') }}</p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('login.page') }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 font-semibold text-on-primary hover:bg-primary-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('instructor_onboarding.sign_in') }}</a>
                        <a href="{{ route('register.page') }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 font-semibold hover:bg-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ __('instructor_onboarding.create_account') }}</a>
                    </div>
                </div>
                <img src="{{ asset('images/public-home-studio-640.webp') }}"
                    srcset="{{ asset('images/public-home-studio-640.webp') }} 640w, {{ asset('images/public-home-studio-1200.webp') }} 1200w"
                    sizes="(min-width: 1024px) 540px, calc(100vw - 2rem)"
                    width="1200" height="800" class="aspect-video size-full object-cover lg:aspect-auto" alt="" fetchpriority="high">
            </section>
        @else
            <header class="rounded-xl border border-border bg-assistant p-5 sm:p-8">
                <h1 class="text-3xl font-extrabold sm:text-4xl">{{ __('instructor_onboarding.title') }}</h1>
                <p class="mt-2 max-w-prose text-muted">{{ __('instructor_onboarding.intro') }}</p>
            </header>
        @endif

        @if(session('teach_notice'))
            <x-ui.alert tone="success" role="status">{{ session('teach_notice') }}</x-ui.alert>
        @endif

        @if($state === 'instructor')
            <x-ui.alert tone="success" data-teach-state="instructor">{{ __('instructor_onboarding.already_instructor') }}</x-ui.alert>
        @elseif($state === 'ineligible')
            <x-ui.alert tone="warning" data-teach-state="ineligible">{{ __('instructor_onboarding.errors.not_eligible') }}</x-ui.alert>
        @elseif($state === 'unverified')
            <x-ui.alert tone="warning" data-teach-state="unverified">{{ __('instructor_onboarding.errors.verify_email') }}</x-ui.alert>
        @elseif($state === 'pending')
            <x-ui.alert tone="info" data-teach-state="pending">{{ __('instructor_onboarding.pending') }}</x-ui.alert>
        @elseif($state === 'form')
            @if($application && $application->status === \App\Domain\Instructors\ApplicationStatus::Rejected)
                <x-ui.alert tone="danger" data-teach-state="rejected">
                    {{ __('instructor_onboarding.rejected') }}
                    @if($application->review_note)<span class="mt-1 block" dir="auto">{{ __('instructor_onboarding.rejected_reason') }}: {{ $application->review_note }}</span>@endif
                </x-ui.alert>
            @endif
            <x-ui.card :title="__('instructor_onboarding.application')" class="shadow-card" data-teach-state="form">
                <form method="post" action="{{ route('instructors.apply') }}" enctype="multipart/form-data" class="relative space-y-5" novalidate>
                    @csrf
                    <x-marketing.honeypot />
                    @if($errors->has('application'))<x-ui.alert tone="danger" role="alert">{{ $errors->first('application') }}</x-ui.alert>@endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.form-field for="full_name" :label="__('instructor_onboarding.fields.full_name')" :error="$field('full_name')">
                            <x-ui.input id="full_name" name="full_name" :value="old('full_name')" autocomplete="name" maxlength="120" required :invalid="$errors->has('full_name')" :aria-describedby="$describedBy('full_name')" />
                        </x-ui.form-field>
                        <x-ui.form-field for="phone" :label="__('instructor_onboarding.fields.phone')" :help="__('contact.phone_help')" :error="$field('phone')">
                            <x-ui.input id="phone" name="phone" type="tel" :value="old('phone')" autocomplete="tel" maxlength="32" :invalid="$errors->has('phone')" :aria-describedby="$describedBy('phone', true)" />
                        </x-ui.form-field>
                        <x-ui.form-field for="discipline" :label="__('instructor_onboarding.fields.discipline')" :error="$field('discipline')">
                            <x-ui.input id="discipline" name="discipline" :value="old('discipline')" maxlength="120" required :invalid="$errors->has('discipline')" :aria-describedby="$describedBy('discipline')" />
                        </x-ui.form-field>
                        <x-ui.form-field for="experience_years" :label="__('instructor_onboarding.fields.experience_years')" :error="$field('experience_years')">
                            <x-ui.input id="experience_years" name="experience_years" type="number" min="0" max="70" dir="ltr" :value="old('experience_years')" :invalid="$errors->has('experience_years')" :aria-describedby="$describedBy('experience_years')" />
                        </x-ui.form-field>
                    </div>
                    <x-ui.form-field for="bio" :label="__('instructor_onboarding.fields.bio')" :help="__('instructor_onboarding.bio_help')" :error="$field('bio')">
                        <x-ui.textarea id="bio" name="bio" rows="6" minlength="30" maxlength="2000" required :invalid="$errors->has('bio')" :aria-describedby="$describedBy('bio', true)">{{ old('bio') }}</x-ui.textarea>
                    </x-ui.form-field>
                    <x-ui.form-field for="portfolio_links" :label="__('instructor_onboarding.fields.portfolio_links')" :help="__('instructor_onboarding.links_help', ['max' => $maxLinks])" :error="$field('portfolio_links')">
                        <x-ui.textarea id="portfolio_links" name="portfolio_links" dir="ltr" rows="4" maxlength="2000" required :invalid="$errors->has('portfolio_links')" :aria-describedby="$describedBy('portfolio_links', true)">{{ old('portfolio_links') }}</x-ui.textarea>
                    </x-ui.form-field>
                    <div class="space-y-2 border-t border-border pt-5">
                        <x-ui.file-upload id="id_document" name="id_document" accept="image/png,image/jpeg,image/webp,application/pdf" :label="__('instructor_onboarding.fields.id_document')" :hint="__('instructor_onboarding.id_hint', ['max' => $maxMegabytes])" :aria-invalid="$errors->has('id_document') ? 'true' : null" :aria-errormessage="$describedBy('id_document')" required />
                        @if($errors->has('id_document'))<p id="id_document-error" class="border-s-4 border-danger ps-2 text-sm font-semibold text-body">{{ $errors->first('id_document') }}</p>@endif
                        <p class="text-sm text-muted">
                            {{ __('instructor_onboarding.privacy_note') }}
                            @if($privacy)
                                <x-ui.link :href="\App\Domain\Seo\PublicUrl::page($privacy, app()->getLocale())">{{ __('contact.privacy_link') }}</x-ui.link>
                            @endif
                        </p>
                        <x-ui.checkbox id="privacy" name="privacy" value="1" :label="__('instructor_onboarding.fields.privacy')" :checked="old('privacy') === '1'" :aria-invalid="$errors->has('privacy') ? 'true' : null" :aria-describedby="$describedBy('privacy')" required />
                        @if($errors->has('privacy'))<p id="privacy-error" class="border-s-4 border-danger ps-2 text-sm font-semibold text-body">{{ $errors->first('privacy') }}</p>@endif
                    </div>
                    <x-ui.button type="submit" class="w-full hover:bg-primary-hover sm:w-auto">{{ __('instructor_onboarding.submit') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
</x-layouts.base>
