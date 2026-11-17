<x-layouts.base variant="student" :title="__('pages.link_title')">
    <h1 class="text-3xl font-bold">{{ __('pages.link_title') }}</h1>
    <p class="mb-6 mt-1 text-muted">{{ __('pages.link_intro') }}</p>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('pages.issue_code')">
            <x-ui.ajax-form :action="url('/guardian/link-codes')" result="issued-code">
                <x-ui.button type="submit">{{ __('pages.issue_code') }}</x-ui.button>
            </x-ui.ajax-form>
            <div id="issued-code" hidden class="mt-4 rounded-lg bg-page p-4">
                <p class="text-sm text-muted">{{ __('pages.code_result') }}</p>
                <p data-result-value tabindex="-1" dir="ltr" class="text-3xl font-extrabold tracking-widest"></p>
                <p class="mt-2 text-sm">{{ __('pages.code_valid') }}</p>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('pages.pending_requests')">
            @forelse($pending as $link)
                <div class="flex flex-wrap items-center justify-between gap-3 py-2">
                    <span class="break-words">{{ __('pages.linked_by', ['name' => $link->guardian->display_name ?: $link->guardian->name]) }}</span>
                    <x-ui.ajax-form :action="url('/guardian/links/'.$link->id.'/confirm')" reload class="!space-y-0">
                        <x-ui.button type="submit">{{ __('pages.confirm_link') }}</x-ui.button>
                    </x-ui.ajax-form>
                </div>
            @empty
                <x-ui.empty-state :title="__('pages.no_pending')" :description="__('pages.no_pending_desc')" />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.base>
