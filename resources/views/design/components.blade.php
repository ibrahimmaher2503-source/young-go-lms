<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('components.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-page font-sans text-body">
    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-8">
        <header class="mb-8">
            <nav aria-label="{{ __('design.preview_options') }}" class="mb-4 flex flex-wrap gap-x-6 gap-y-2">
                <x-ui.link :href="route('design.tokens', ['locale' => $locale, 'theme' => $theme])">{{ __('design.title') }}</x-ui.link>
                <x-ui.link :href="route('design.components', ['locale' => $locale === 'ar' ? 'en' : 'ar', 'theme' => $theme])">{{ __('design.switch_language') }}</x-ui.link>
                <x-ui.link :href="route('design.components', ['locale' => $locale, 'theme' => $theme === 'dark' ? 'light' : 'dark'])">{{ __('design.switch_'.$theme) }}</x-ui.link>
            </nav>
            <h1 class="text-3xl font-bold sm:text-4xl">{{ __('components.title') }}</h1>
            <p class="mt-2 max-w-prose text-muted">{{ __('components.intro') }}</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.card :title="__('components.controls')">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach(['primary', 'secondary', 'ghost', 'danger'] as $variant)
                        <x-ui.button :variant="$variant">{{ __('components.'.$variant) }}</x-ui.button>
                    @endforeach
                    <x-ui.button size="sm" variant="secondary">{{ __('components.small') }}</x-ui.button>
                    <x-ui.button size="lg"><x-slot:icon><x-ui.icon name="check" /></x-slot:icon>{{ __('components.large') }}</x-ui.button>
                    <x-ui.button :loading="true">{{ __('components.loading') }}</x-ui.button>
                    <x-ui.button disabled>{{ __('components.disabled') }}</x-ui.button>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-ui.chip :selected="true">{{ __('components.selected') }}</x-ui.chip>
                    <x-ui.chip>{{ __('components.examples') }}</x-ui.chip>
                    <x-ui.avatar :name="__('components.learner')" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('components.forms')" class="space-y-3">
                <x-ui.form-field for="gallery-email" :label="__('components.email')" :help="__('components.email_hint')" :error="__('components.email_error')">
                    <x-ui.input id="gallery-email" type="email" value="demo" :invalid="true" aria-describedby="gallery-email-help gallery-email-error" />
                </x-ui.form-field>
                <x-ui.form-field for="gallery-notes" :label="__('components.notes')">
                    <x-ui.textarea id="gallery-notes" rows="2" disabled />
                </x-ui.form-field>
                <x-ui.form-field for="gallery-format" :label="__('components.option')">
                    <x-ui.select id="gallery-format">
                        <option>{{ __('components.recorded') }}</option>
                        <option>{{ __('components.live') }}</option>
                    </x-ui.select>
                </x-ui.form-field>
                <div class="flex flex-wrap gap-x-4">
                    <x-ui.checkbox id="gallery-newsletter" :label="__('components.newsletter')" checked />
                    <x-ui.radio id="gallery-recorded" name="gallery-learning" :label="__('components.recorded')" checked />
                    <x-ui.radio id="gallery-live" name="gallery-learning" :label="__('components.live')" />
                    <x-ui.switch id="gallery-notify" :label="__('components.notify')" />
                </div>
                <x-ui.file-upload id="gallery-upload" :label="__('components.attachment')" :hint="__('components.attachment_hint')" accept=".pdf,application/pdf" :progress="35" />
            </x-ui.card>

            <x-ui.card :title="__('components.panels')" class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    <x-ui.modal id="gallery-modal" :title="__('components.confirm')" :trigger="__('components.open_modal')">
                        <p>{{ __('components.confirm_body') }}</p>
                        <form method="dialog" class="mt-4"><x-ui.button type="submit">{{ __('components.close') }}</x-ui.button></form>
                    </x-ui.modal>
                    <x-ui.drawer id="gallery-drawer" :title="__('components.assistant')" :trigger="__('components.open_drawer')">
                        <x-ui.chip :selected="true">{{ __('components.examples') }}</x-ui.chip>
                        <x-ui.form-field for="gallery-answer" :label="__('components.notes')" class="mt-3">
                            <x-ui.textarea id="gallery-answer" rows="2">
                                <x-slot:streaming>{{ __('components.assistant_text') }}</x-slot:streaming>
                            </x-ui.textarea>
                        </x-ui.form-field>
                    </x-ui.drawer>
                    <x-ui.dropdown id="gallery-dropdown" :label="__('components.more')">
                        <x-ui.link :href="route('design.tokens', ['locale' => $locale])">{{ __('design.title') }}</x-ui.link>
                    </x-ui.dropdown>
                </div>
                <x-ui.tabs id="gallery-tabs" :label="__('components.preview')" :items="['overview' => __('components.overview'), 'examples' => __('components.examples')]">
                    <x-slot:overview>{{ __('components.intro') }}</x-slot:overview>
                    <x-slot:examples>{{ __('components.details_body') }}</x-slot:examples>
                </x-ui.tabs>
                <x-ui.accordion :label="__('components.details')" :open="true">{{ __('components.details_body') }}</x-ui.accordion>
                <x-ui.tooltip id="gallery-tooltip" :text="__('components.tooltip')">{{ __('components.help') }}</x-ui.tooltip>
            </x-ui.card>

            <x-ui.card :title="__('components.feedback')" class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    @foreach(['neutral' => 'disabled', 'success' => 'ready', 'warning' => 'warning', 'danger' => 'error', 'info' => 'info'] as $tone => $text)
                        <x-ui.badge :tone="$tone">{{ __('components.'.$text) }}</x-ui.badge>
                    @endforeach
                </div>
                <x-ui.alert tone="warning">{{ __('components.warning') }}</x-ui.alert>
                <x-ui.toast tone="success">{{ __('components.saved') }}</x-ui.toast>
                <x-ui.progress-bar :label="__('components.progress')" :value="66" />
                <x-ui.progress-ring :label="__('components.progress')" :value="66" />
                <x-ui.skeleton />
                <x-ui.empty-state :title="__('components.empty')" :description="__('components.empty_hint')" />
            </x-ui.card>

            <x-ui.card :title="__('components.navigation')" class="space-y-3 sm:col-span-2">
                <x-ui.breadcrumb :items="[['label' => __('components.home'), 'url' => route('design.tokens')], ['label' => __('components.title')]]" />
                <x-ui.pagination :links="[['label' => __('components.previous'), 'url' => route('design.components')], ['label' => '2', 'current' => true], ['label' => __('components.next'), 'url' => route('design.components')]]" />
                <x-ui.stepper :steps="[__('components.choose'), __('components.review'), __('components.finish')]" :current="2" />
                <dl class="grid gap-2 sm:grid-cols-2">
                    <div><dt>{{ __('components.price') }}</dt><dd class="font-semibold"><x-ui.price :money="\App\Domain\Shared\Money\Money::ofMinor(25000, 'EGP')" :market="\App\Domain\Shared\Markets\Market::Egypt" /></dd></div>
                    <div><dt>{{ __('components.date') }}</dt><dd><x-ui.date :instant="new \DateTimeImmutable('2026-11-16T11:00:00Z')" :market="\App\Domain\Shared\Markets\Market::Egypt" /></dd></div>
                </dl>
                <x-ui.rating :value="4.5" />
                <div><x-ui.bdi-text dir="ltr">learner@example.test</x-ui.bdi-text></div>
                <x-ui.locale-switcher :locale="$locale" />
            </x-ui.card>
        </div>
    </main>
</body>
</html>
