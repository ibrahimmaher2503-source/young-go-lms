@props(['sections', 'prefix', 'heading', 'icons' => [], 'marker' => 'book'])
@php($contents = $sections->filter(fn ($section) => filled($section['title'])))

<div @class(['info-policy' => $contents->isNotEmpty()])>
    @if($contents->isNotEmpty())
        <nav class="info-card info-policy-nav" aria-labelledby="{{ $prefix }}-contents">
            <div class="info-card-row">
                <span class="info-icon"><x-ui.icon name="list"/></span>
                <h2 id="{{ $prefix }}-contents">{{ $heading }}</h2>
            </div>
            <ul>
                @foreach($contents as $index => $section)
                    <li><x-ui.link :href="'#'.$prefix.'-section-'.($index + 1)" dir="auto">{{ $section['title'] }}</x-ui.link></li>
                @endforeach
            </ul>
            <x-ui.icon :name="$marker" class="info-policy-watermark"/>
        </nav>
    @endif

    <div @class(['info-policy-sections' => $contents->isNotEmpty()])>
        @foreach($sections as $section)
            <section id="{{ $prefix }}-section-{{ $loop->iteration }}" @class(['info-card', 'info-card-row' => $section['title'], 'sm:col-span-2' => ! $section['title']]) tabindex="-1">
                @if($section['title'])<span class="info-icon"><x-ui.icon :name="$icons[$loop->index] ?? 'file'"/></span>@endif
                <div class="min-w-0">
                    @if($section['title'])<h2 dir="auto">{{ $section['title'] }}</h2>@endif
                    <p class="whitespace-pre-line" dir="auto">{{ $section['body'] }}</p>
                </div>
            </section>
        @endforeach
    </div>
</div>
