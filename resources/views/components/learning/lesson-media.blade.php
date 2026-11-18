{{-- Props: descriptor (PlaybackDescriptor), title. One renderer for every media provider. --}}
@props(['descriptor', 'title', 'managedStage' => false])
@php($stageFullscreen = $managedStage && ($descriptor->provider === 'vimeo' || \App\Domain\Media\Watermark::applies($descriptor->tier)))
@switch($descriptor->type)
    @case('video')
        <video class="block aspect-video w-full bg-black object-contain" controls preload="metadata" playsinline src="{{ $descriptor->url }}" aria-label="{{ $title }}" data-player-media controlslist="{{ $stageFullscreen ? 'nodownload nofullscreen' : 'nodownload' }}" @if($stageFullscreen) data-stage-fullscreen-only @endif>
            @foreach($descriptor->tracks as $track)
                <track kind="captions" src="{{ $track['url'] }}" srclang="{{ $track['locale'] }}" label="{{ $track['label'] }}" @if($track['default']) default @endif>
            @endforeach
        </video>
        @break
    @case('audio')
        <div class="p-4"><audio class="w-full" controls preload="metadata" src="{{ $descriptor->url }}" aria-label="{{ $title }}" data-player-media></audio></div>
        @break
    @case('embed')
        <iframe class="block aspect-video w-full" src="{{ $descriptor->embedUrl }}" title="{{ $title }}" loading="lazy"
            @if($stageFullscreen) allow="fullscreen 'none'" @else allowfullscreen @endif
            @if($managedStage && $descriptor->provider === 'vimeo') data-player-vimeo @endif
            @if($descriptor->sandbox) sandbox="{{ $descriptor->sandbox }}" @endif referrerpolicy="strict-origin-when-cross-origin"></iframe>
        @break
    @case('document')
        <iframe class="h-[70vh] w-full" src="{{ $descriptor->url }}" title="{{ $title }}" loading="lazy"></iframe>
        @break
    @case('image')
        <img class="mx-auto block max-h-[70vh] w-auto max-w-full" src="{{ $descriptor->url }}" alt="{{ $title }}">
        @break
    @case('text')
        <div class="prose max-w-none p-4 break-words" dir="auto">{!! nl2br(e((string) $descriptor->text)) !!}</div>
        @break
@endswitch
