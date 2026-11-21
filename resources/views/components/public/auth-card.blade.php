@props(['title'])
<section class="min-w-0 rounded-card border border-border bg-surface p-5 shadow-card sm:p-8" data-auth-card>
    <header class="mb-6 flex flex-col items-center gap-3 text-center">
        <x-public.brand :href="\App\Domain\Seo\PublicUrl::home(app()->getLocale())"/>
        <h1 class="wrap-anywhere text-2xl font-extrabold leading-tight sm:text-3xl">{{ $title }}</h1>
    </header>
    {{ $slot }}
    <footer class="mt-5 border-t border-border pt-4">
        {{ $footer ?? '' }}
        <div class="flex flex-wrap items-center justify-center gap-2">
            <x-ui.link :href="\App\Domain\Seo\PublicUrl::contact(app()->getLocale())" class="text-sm">{{ __('public-site.help') }}</x-ui.link>
            <x-ui.theme-toggle class="border-transparent! bg-transparent! text-muted!"/>
            <x-ui.locale-switcher variant="ghost"/>
        </div>
    </footer>
</section>
