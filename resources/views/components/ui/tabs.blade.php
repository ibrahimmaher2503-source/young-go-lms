{{-- Props: id, label, items (id => translated label). Pass a named slot for each key. --}}
@props(['id', 'label', 'items'])
<div data-ui-tabs {{ $attributes }}>
    <div role="tablist" aria-label="{{ $label }}" class="flex flex-wrap gap-2">
        @foreach($items as $key => $text)
            <button id="{{ $id }}-tab-{{ $key }}" type="button" role="tab" aria-controls="{{ $id }}-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}" class="min-h-11 rounded-md px-3 font-semibold text-body aria-selected:bg-primary aria-selected:text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ $text }}</button>
        @endforeach
    </div>
    @foreach($items as $key => $text)
        <div id="{{ $id }}-panel-{{ $key }}" role="tabpanel" aria-labelledby="{{ $id }}-tab-{{ $key }}" tabindex="0" @if(!$loop->first) hidden @endif class="mt-3 rounded-md p-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ ${$key} ?? '' }}</div>
    @endforeach
</div>
