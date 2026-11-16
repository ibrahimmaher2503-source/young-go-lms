<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('design.title') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-page font-sans text-body">
    <main class="mx-auto max-w-5xl px-5 py-10 sm:px-10 sm:py-16">
        <nav aria-label="{{ __('design.preview_options') }}" class="mb-8 flex flex-wrap gap-3">
            <a href="{{ route('design.tokens', ['locale' => $locale === 'ar' ? 'en' : 'ar', 'theme' => $theme]) }}" class="inline-flex min-h-11 items-center rounded-md bg-surface px-4 py-2 text-body underline">
                {{ __('design.switch_language') }}
            </a>
            <a href="{{ route('design.tokens', ['locale' => $locale, 'theme' => $theme === 'dark' ? 'light' : 'dark']) }}" class="inline-flex min-h-11 items-center rounded-md bg-surface px-4 py-2 text-body underline">
                {{ __('design.switch_'.$theme) }}
            </a>
        </nav>

        <header class="rounded-xl bg-surface p-6 sm:p-10">
            <p class="text-sm font-semibold text-muted">{{ __('design.brand') }}</p>
            <h1 class="mt-2 text-3xl font-bold leading-tight sm:text-4xl">{{ __('design.title') }}</h1>
            <p class="mt-4 max-w-prose leading-relaxed text-muted">{{ __('design.provisional') }}</p>
        </header>

        <section class="mt-8" aria-labelledby="palette">
            <h2 id="palette" class="mb-4 text-xl font-semibold">{{ __('design.palette') }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg bg-primary p-4 font-semibold text-on-primary">{{ __('design.primary') }}</div>
                <div class="rounded-lg bg-accent p-4 font-semibold text-on-accent">{{ __('design.accent') }}</div>
                <div class="rounded-lg bg-surface p-4 font-semibold text-body">{{ __('design.surface') }}</div>
                <div class="rounded-lg bg-info p-4 font-semibold text-on-info">{{ __('design.info') }}</div>
                <div class="rounded-lg bg-success p-4 font-semibold text-on-success">{{ __('design.success') }}</div>
                <div class="rounded-lg bg-warning p-4 font-semibold text-on-warning">{{ __('design.warning') }}</div>
                <div class="rounded-lg bg-danger p-4 font-semibold text-on-danger">{{ __('design.danger') }}</div>
            </div>
        </section>

        <section aria-labelledby="assistant" class="mt-8 rounded-xl bg-assistant p-6 sm:p-8">
            <h2 id="assistant" class="text-xl font-semibold">{{ __('design.assistant') }}</h2>
            <ul class="mt-4 flex flex-wrap gap-2">
                <li class="rounded-full bg-ai-waiting px-3 py-1 text-sm text-on-ai-waiting">{{ __('design.ai_waiting') }}</li>
                <li class="rounded-full bg-ai-processing px-3 py-1 text-sm text-on-ai-processing">{{ __('design.ai_processing') }}</li>
                <li class="rounded-full bg-ai-review px-3 py-1 text-sm text-on-ai-review">{{ __('design.ai_review') }}</li>
                <li class="rounded-full bg-ai-failed px-3 py-1 text-sm text-on-ai-failed">{{ __('design.ai_failed') }}</li>
                <li class="rounded-full bg-ai-ready px-3 py-1 text-sm text-on-ai-ready">{{ __('design.ai_ready') }}</li>
            </ul>
        </section>
    </main>
</body>
</html>
