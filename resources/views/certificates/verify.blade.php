{{-- Public verification page. Safe fields only: name, course, completion date, number, status. No email, ids or revoke reason. --}}
<x-layouts.base variant="public" :minimal="true" :title="__('certificates.verify.title')">
    <div class="mx-auto max-w-2xl py-6">
        <x-ui.card class="space-y-4" data-verify-result>
            <h1 class="text-2xl font-bold">{{ __('certificates.verify.title') }}</h1>
            <x-ui.badge :tone="$certificate->isValid() ? 'success' : 'danger'" data-verify-status="{{ $certificate->isValid() ? 'valid' : 'revoked' }}">{{ $certificate->isValid() ? __('certificates.verify.valid') : __('certificates.verify.revoked') }}</x-ui.badge>
            @unless($certificate->isValid())
                <p class="text-muted">{{ __('certificates.verify.revoked_note') }}</p>
            @endunless
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="text-sm text-muted">{{ __('certificates.verify.name') }}</dt><dd class="break-words font-semibold" data-field="name">{{ $certificate->learner_name }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('certificates.verify.course') }}</dt><dd class="break-words font-semibold" data-field="course">{{ $certificate->titleIn(app()->getLocale()) }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('certificates.verify.date') }}</dt><dd class="font-semibold" data-field="date">{{ $certificate->completed_on->isoFormat('LL') }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('certificates.verify.number') }}</dt><dd class="font-semibold" data-field="number"><bdi dir="ltr">{{ $certificate->number }}</bdi></dd></div>
            </dl>
        </x-ui.card>
    </div>
</x-layouts.base>
