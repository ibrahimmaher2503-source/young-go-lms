@php($field = fn (string $name) => $errors->first($name))
<x-layouts.base variant="public" :title="__('instructor_onboarding.title')">
    <div class="mx-auto max-w-2xl space-y-6" data-teach-page>
        <header class="space-y-2">
            <h1 class="text-3xl font-bold">{{ __('instructor_onboarding.title') }}</h1>
            <p class="text-muted">{{ __('instructor_onboarding.intro') }}</p>
        </header>

        @if(session('teach_notice'))
            <x-ui.alert tone="success" role="status">{{ session('teach_notice') }}</x-ui.alert>
        @endif

        @if($state === 'guest')
            <x-ui.card data-teach-state="guest">
                <p class="mb-4">{{ __('instructor_onboarding.sign_in_needed') }}</p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('login.page') }}" class="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 font-semibold text-on-primary">{{ __('instructor_onboarding.sign_in') }}</a>
                    <a href="{{ route('register.page') }}" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 font-semibold">{{ __('instructor_onboarding.create_account') }}</a>
                </div>
            </x-ui.card>
        @elseif($state === 'instructor')
            <x-ui.alert tone="success" data-teach-state="instructor">{{ __('instructor_onboarding.already_instructor') }}</x-ui.alert>
        @elseif($state === 'ineligible')
            <x-ui.alert tone="warning" data-teach-state="ineligible">{{ __('instructor_onboarding.errors.not_eligible') }}</x-ui.alert>
        @elseif($state === 'unverified')
            <x-ui.alert tone="warning" data-teach-state="unverified">{{ __('instructor_onboarding.errors.verify_email') }}</x-ui.alert>
        @elseif($state === 'pending')
            <x-ui.alert tone="info" data-teach-state="pending">{{ __('instructor_onboarding.pending') }}</x-ui.alert>
        @else
            @if($application && $application->status === \App\Domain\Instructors\ApplicationStatus::Rejected)
                <x-ui.alert tone="danger" data-teach-state="rejected">
                    {{ __('instructor_onboarding.rejected') }}
                    @if($application->review_note)<span class="mt-1 block" dir="auto">{{ __('instructor_onboarding.rejected_reason') }}: {{ $application->review_note }}</span>@endif
                </x-ui.alert>
            @endif
            <x-ui.card data-teach-state="form">
                <form method="post" action="{{ route('instructors.apply') }}" enctype="multipart/form-data" class="relative space-y-4" novalidate>
                    @csrf
                    <x-marketing.honeypot />
                    @if($errors->has('application'))<x-ui.alert tone="danger" role="alert">{{ $errors->first('application') }}</x-ui.alert>@endif
                    <x-ui.form-field for="full_name" :label="__('instructor_onboarding.fields.full_name')" :error="$field('full_name')">
                        <x-ui.input id="full_name" name="full_name" :value="old('full_name')" autocomplete="name" maxlength="120" required :invalid="$errors->has('full_name')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="phone" :label="__('instructor_onboarding.fields.phone')" :help="__('contact.phone_help')" :error="$field('phone')">
                        <x-ui.input id="phone" name="phone" type="tel" dir="ltr" :value="old('phone')" autocomplete="tel" maxlength="32" :invalid="$errors->has('phone')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="discipline" :label="__('instructor_onboarding.fields.discipline')" :error="$field('discipline')">
                        <x-ui.input id="discipline" name="discipline" :value="old('discipline')" maxlength="120" required :invalid="$errors->has('discipline')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="experience_years" :label="__('instructor_onboarding.fields.experience_years')" :error="$field('experience_years')">
                        <x-ui.input id="experience_years" name="experience_years" type="number" min="0" max="70" dir="ltr" :value="old('experience_years')" :invalid="$errors->has('experience_years')" />
                    </x-ui.form-field>
                    <x-ui.form-field for="bio" :label="__('instructor_onboarding.fields.bio')" :help="__('instructor_onboarding.bio_help')" :error="$field('bio')">
                        <x-ui.textarea id="bio" name="bio" rows="6" minlength="30" maxlength="2000" required :invalid="$errors->has('bio')">{{ old('bio') }}</x-ui.textarea>
                    </x-ui.form-field>
                    <x-ui.form-field for="portfolio_links" :label="__('instructor_onboarding.fields.portfolio_links')" :help="__('instructor_onboarding.links_help', ['max' => $maxLinks])" :error="$field('portfolio_links')">
                        <x-ui.textarea id="portfolio_links" name="portfolio_links" dir="ltr" rows="4" maxlength="2000" required :invalid="$errors->has('portfolio_links')">{{ old('portfolio_links') }}</x-ui.textarea>
                    </x-ui.form-field>
                    <x-ui.file-upload id="id_document" name="id_document" accept="image/png,image/jpeg,image/webp,application/pdf" :label="__('instructor_onboarding.fields.id_document')" :hint="__('instructor_onboarding.id_hint', ['max' => $maxMegabytes])" required />
                    @if($errors->has('id_document'))<p class="border-s-4 border-danger ps-2 text-sm font-semibold text-body">{{ $errors->first('id_document') }}</p>@endif
                    <p class="text-sm text-muted">{{ __('instructor_onboarding.privacy_note') }} <x-ui.link :href="route('cms.show', ['slug' => 'privacy'])">{{ __('contact.privacy_link') }}</x-ui.link></p>
                    <div>
                        <x-ui.checkbox id="privacy" name="privacy" value="1" :label="__('instructor_onboarding.fields.privacy')" :checked="old('privacy') === '1'" required />
                        @if($errors->has('privacy'))<p class="border-s-4 border-danger ps-2 text-sm font-semibold text-body">{{ $errors->first('privacy') }}</p>@endif
                    </div>
                    <x-ui.button type="submit" variant="primary">{{ __('instructor_onboarding.submit') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
</x-layouts.base>
