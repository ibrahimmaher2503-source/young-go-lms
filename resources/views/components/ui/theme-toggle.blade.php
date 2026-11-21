<button type="button" data-theme-toggle aria-label="{{ __('common.toggle_theme') }}" title="{{ __('common.toggle_theme') }}" {{ $attributes->class('inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-md border border-border bg-surface text-body transition-colors hover:bg-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary') }}>
    <x-ui.icon name="sun" class="hidden size-5 dark:block" />
    <x-ui.icon name="moon" class="block size-5 dark:hidden" />
</button>
