{{-- The public view reads only the certificate's issuance snapshot. --}}
<x-layouts.base variant="public" :minimal="true" :full="true" :title="__('certificates.verify.title')">
    <x-slot:minimal-header>
        <x-ui.locale-switcher />
    </x-slot:minimal-header>
    <x-slot:brand-aside>
        <span class="hidden border-s border-border ps-3 sm:block"><x-ui.badge tone="info">{{ __('certificates.verify.registry') }}</x-ui.badge></span>
    </x-slot:brand-aside>

    <div class="public-page-stack mx-auto max-w-7xl">
        <header class="space-y-2">
            <h1 class="text-3xl font-extrabold sm:text-4xl">{{ __('certificates.verify.title') }}</h1>
            <p class="max-w-prose text-muted">{{ __('certificates.verify.description') }}</p>
        </header>

        <div @class(['grid items-stretch gap-5 sm:gap-6', 'lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]' => $certificate->isValid(), 'max-w-2xl' => ! $certificate->isValid()])>
            <x-ui.card :class="'flex flex-col gap-5 shadow-card'.($certificate->isValid() ? ' lg:col-start-2 lg:row-start-1' : '')" data-verify-result>
                <x-ui.badge class="min-h-12 w-full justify-center gap-2 rounded-lg! py-3 text-center text-base" :tone="$certificate->isValid() ? 'success' : 'danger'" data-verify-status="{{ $certificate->isValid() ? 'valid' : 'revoked' }}">
                    <x-ui.icon :name="$certificate->isValid() ? 'check' : 'close'" />
                    {{ $certificate->isValid() ? __('certificates.verify.valid') : __('certificates.verify.revoked') }}
                </x-ui.badge>
                @if($qr)
                    <figure class="grid justify-items-center gap-2 text-center text-sm text-muted">
                        <img class="size-28 rounded-lg bg-qr p-1" src="{{ $qr }}" alt="{{ __('certificates.pdf.qr_alt') }}" width="112" height="112">
                        <figcaption>{{ __('certificates.verify.scan') }}</figcaption>
                    </figure>
                @else
                    <p class="text-muted">{{ __('certificates.verify.revoked_note') }}</p>
                @endif

                <dl class="divide-y divide-border border-y border-border text-sm">
                    <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 py-2.5"><dt class="text-muted">{{ __('certificates.verify.name') }}</dt><dd class="text-end font-semibold wrap-anywhere" data-field="name"><bdi>{{ $certificate->nameIn(app()->getLocale()) }}</bdi></dd></div>
                    <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 py-2.5"><dt class="text-muted">{{ __('certificates.verify.course') }}</dt><dd class="text-end font-semibold wrap-anywhere" data-field="course"><bdi>{{ $certificate->titleIn(app()->getLocale()) }}</bdi></dd></div>
                    <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 py-2.5"><dt class="text-muted">{{ __('certificates.verify.number') }}</dt><dd class="text-end font-semibold wrap-anywhere" data-field="number"><bdi dir="ltr">{{ $certificate->number }}</bdi></dd></div>
                    <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 py-2.5"><dt class="text-muted">{{ __('certificates.verify.date') }}</dt><dd class="text-end font-semibold wrap-anywhere" data-field="date"><time datetime="{{ $certificate->completed_on->toDateString() }}">{{ $certificate->completed_on->isoFormat('LL') }}</time></dd></div>
                </dl>

                <div class="mt-auto">
                    <x-ui.button variant="secondary" class="w-full" data-copy-text="{{ $certificate->verifyUrl() }}" data-copied="{{ __('certificates.verify.copied') }}">{{ __('certificates.verify.copy_link') }}</x-ui.button>
                    <p role="status" class="text-center text-sm text-muted" data-copy-status></p>
                </div>
            </x-ui.card>

            @if($certificate->isValid())
                <x-ui.card class="flex shadow-card lg:col-start-1 lg:row-start-1" data-certificate-preview>
                    <div class="flex min-w-0 flex-1 rounded-xl border-4 border-double border-primary bg-assistant p-2 text-center sm:p-3">
                        <div class="grid w-full min-w-0 content-center justify-items-center gap-3 rounded-lg border border-border bg-surface px-4 py-8 sm:min-h-105 sm:px-8 sm:py-10">
                            <h2 class="text-xl font-extrabold text-primary sm:text-3xl">{{ __('certificates.pdf.heading') }}</h2>
                            <p class="mt-1 text-sm text-muted sm:text-base">{{ __('certificates.pdf.awarded_to') }}</p>
                            <p class="border-b border-border pb-3 text-2xl font-extrabold wrap-anywhere sm:text-4xl"><bdi>{{ $certificate->nameIn(app()->getLocale()) }}</bdi></p>
                            <p class="max-w-prose font-semibold wrap-anywhere">{{ __('certificates.pdf.for_completing') }} <bdi>{{ $certificate->titleIn(app()->getLocale()) }}</bdi></p>
                            <time class="text-muted" datetime="{{ $certificate->completed_on->toDateString() }}">{{ $certificate->completed_on->isoFormat('LL') }}</time>
                            <span class="mt-3 grid size-14 place-items-center rounded-full border-4 border-double border-primary bg-assistant text-primary sm:size-18">
                                <x-ui.icon name="award" class="size-7 sm:size-9"/>
                            </span>
                            <bdi class="text-sm font-semibold text-muted" dir="ltr">{{ $certificate->number }}</bdi>
                        </div>
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.base>
