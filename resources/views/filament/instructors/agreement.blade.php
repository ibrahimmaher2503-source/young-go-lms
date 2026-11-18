<x-filament-panels::page>
    <x-filament::section :heading="__('instructor_onboarding.agreement.page_title')" data-instructor-agreement>
        @if($agreement === null)
            <p>{{ __('instructor_onboarding.agreement.none_published') }}</p>
        @else
            <p class="text-sm" data-agreement-version>{{ __('instructor_onboarding.agreement.version') }}: <bdi dir="ltr">{{ $agreement->version }}</bdi></p>
            @if(str_contains($agreement->version, 'draft'))
                <p class="mt-2 text-sm font-semibold" data-agreement-draft>{{ __('instructor_onboarding.agreement.draft_notice') }}</p>
            @endif
            <h2 class="mt-4 text-lg font-bold">{{ $agreement->getTranslation('title', app()->getLocale()) }}</h2>
            <div class="mt-2 whitespace-pre-line" dir="auto">{{ $agreement->getTranslation('body', app()->getLocale()) }}</div>
            <div class="mt-6">
                @if($accepted)
                    <p class="font-semibold" data-agreement-accepted>{{ __('instructor_onboarding.agreement.already_accepted') }}</p>
                @else
                    <p class="mb-3 font-semibold" data-agreement-required>{{ __('instructor_onboarding.agreement.required_notice') }}</p>
                    <x-filament::button type="button" wire:click="mountAction('accept')" data-action="accept">{{ __('instructor_onboarding.agreement.accept') }}</x-filament::button>
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
