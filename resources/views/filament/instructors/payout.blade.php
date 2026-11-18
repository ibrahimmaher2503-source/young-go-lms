<x-filament-panels::page>
    <x-filament::section :heading="__('instructor_onboarding.payout.page_title')" :description="__('instructor_onboarding.payout.intro')" data-payout-profile>
        @if($method === null)
            <p data-payout-missing>{{ __('instructor_onboarding.payout.none') }}</p>
        @else
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium">{{ __('instructor_onboarding.payout.method') }}</dt>
                    <dd>{{ __('instructor_onboarding.payout.method_'.$method) }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium">{{ __('instructor_onboarding.payout.col_identifier') }}</dt>
                    <dd data-payout-masked><bdi dir="ltr">{{ $masked }}</bdi></dd>
                </div>
            </dl>
        @endif
        <div class="mt-4">
            <x-filament::button type="button" wire:click="mountAction('edit')" data-action="edit">{{ __('instructor_onboarding.payout.edit') }}</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
