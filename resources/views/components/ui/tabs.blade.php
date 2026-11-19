{{-- Props: id, label, items (id => translated label), variant (pills|underline: underline marks the active tab with a primary bottom border). Pass a named slot for each key. --}}
@props(['id', 'label', 'items', 'variant' => 'pills'])
<div data-ui-tabs {{ $attributes }}>
    <div role="tablist" aria-label="{{ $label }}" @class(['flex gap-2', 'flex-wrap' => $variant !== 'underline', 'flex-nowrap gap-x-1 overflow-x-auto overscroll-x-contain border-b border-border' => $variant === 'underline'])>
        @foreach($items as $key => $text)
            <button id="{{ $id }}-tab-{{ $key }}" type="button" role="tab" aria-controls="{{ $id }}-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}" @class(['min-h-11 px-3 font-semibold text-body focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', 'rounded-md aria-selected:bg-primary aria-selected:text-on-primary' => $variant !== 'underline', '-mb-px shrink-0 whitespace-nowrap border-b-2 border-transparent aria-selected:border-primary aria-selected:text-primary' => $variant === 'underline'])>{{ $text }}</button>
        @endforeach
    </div>
    @foreach($items as $key => $text)
        <div id="{{ $id }}-panel-{{ $key }}" role="tabpanel" aria-labelledby="{{ $id }}-tab-{{ $key }}" tabindex="0" @if(!$loop->first) hidden @endif class="mt-3 rounded-md p-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ ${$key} ?? '' }}</div>
    @endforeach
</div>
