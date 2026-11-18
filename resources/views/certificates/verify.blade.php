{{-- The public view reads only the certificate's issuance snapshot. --}}
<x-layouts.base variant="public" :minimal="true" :full="true" :title="__('certificates.verify.title')">
    <x-slot:minimal-header>
        <x-ui.locale-switcher />
    </x-slot:minimal-header>
    <x-slot:brand-aside>
        <span class="hidden border-s border-border ps-3 sm:block"><x-ui.badge tone="info">{{ __('certificates.verify.registry') }}</x-ui.badge></span>
    </x-slot:brand-aside>
    <div class="mb-7 space-y-2 sm:mx-2.5">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ __('certificates.verify.title') }}</h1>
        <p class="text-muted">{{ __('certificates.verify.description') }}</p>
    </div>
    <div @class(['grid gap-5 sm:mx-2.5', 'lg:grid-cols-[3fr_2fr]' => $certificate->isValid()])>
        @if($certificate->isValid())
            <x-ui.card data-certificate-preview>
                <div class="grid min-h-80 content-center gap-4 rounded-lg border-4 border-double border-primary p-6 text-center sm:min-h-120 sm:p-12">
                    <h2 class="text-xl font-bold text-primary sm:text-3xl">{{ __('certificates.pdf.heading') }}</h2>
                    <p class="text-sm text-muted">{{ __('certificates.pdf.awarded_to') }}</p>
                    <p class="break-words text-2xl font-extrabold sm:text-4xl">{{ $certificate->nameIn(app()->getLocale()) }}</p>
                    <p class="break-words font-semibold">{{ $certificate->titleIn(app()->getLocale()) }}</p>
                    <p class="text-muted">{{ $certificate->completed_on->isoFormat('LL') }}</p>
                    <x-ui.icon name="book" class="mx-auto mt-3 size-9 text-primary" />
                </div>
            </x-ui.card>
        @endif
        <x-ui.card class="flex flex-col gap-5" data-verify-result>
            <div class="text-center">
                <x-ui.badge class="w-full justify-center py-3 text-base" :tone="$certificate->isValid() ? 'success' : 'danger'" data-verify-status="{{ $certificate->isValid() ? 'valid' : 'revoked' }}">
                    @if($qr)<x-ui.icon name="check" />@endif
                    {{ $certificate->isValid() ? __('certificates.verify.valid') : __('certificates.verify.revoked') }}
                </x-ui.badge>
            </div>
            @if($qr)
                <figure class="grid justify-items-center gap-2 text-center text-sm text-muted">
                    <img class="size-28 bg-white p-1" src="{{ $qr }}" alt="{{ __('certificates.pdf.qr_alt') }}">
                    <figcaption>{{ __('certificates.verify.scan') }}</figcaption>
                </figure>
            @else
                <p class="text-muted">{{ __('certificates.verify.revoked_note') }}</p>
            @endif
            <dl class="grid gap-3 text-sm">
                <div class="flex flex-wrap justify-between gap-x-3"><dt class="text-muted">{{ __('certificates.verify.name') }}</dt><dd class="break-words font-semibold" data-field="name">{{ $certificate->nameIn(app()->getLocale()) }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-3"><dt class="text-muted">{{ __('certificates.verify.course') }}</dt><dd class="break-words font-semibold" data-field="course">{{ $certificate->titleIn(app()->getLocale()) }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-3"><dt class="text-muted">{{ __('certificates.verify.date') }}</dt><dd class="font-semibold" data-field="date">{{ $certificate->completed_on->isoFormat('LL') }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-3"><dt class="text-muted">{{ __('certificates.verify.number') }}</dt><dd class="font-semibold" data-field="number"><bdi dir="ltr">{{ $certificate->number }}</bdi></dd></div>
            </dl>
            <div class="mt-auto">
                <x-ui.button variant="secondary" class="w-full" data-copy-text="{{ $certificate->verifyUrl() }}" data-copied="{{ __('certificates.verify.copied') }}">{{ __('certificates.verify.copy_link') }}</x-ui.button>
                <p role="status" class="text-center text-sm text-muted" data-copy-status></p>
            </div>
        </x-ui.card>
    </div>
</x-layouts.base>
