@props(['title', 'description' => null, 'image', 'quiet' => false, 'icon' => 'book'])

<section {{ $attributes->class(['info-banner', 'info-banner-quiet' => $quiet, 'info-banner-strong' => ! $quiet]) }}>
    <div @class(['info-banner-copy', 'info-card-row' => $quiet])>
        @if($quiet)<span class="info-icon"><x-ui.icon :name="$icon"/></span>@endif
        <div class="min-w-0">
            <h2 class="info-section-title">{{ $title }}</h2>
            @if(filled($description))<p class="mt-1.5">{{ $description }}</p>@endif
            {{ $slot }}
        </div>
    </div>
    <img class="info-banner-art" src="{{ asset($image) }}" alt="" width="416" height="180" loading="lazy" decoding="async">
</section>
