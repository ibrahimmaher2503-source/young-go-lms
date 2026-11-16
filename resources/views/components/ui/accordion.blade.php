{{-- Props: label, open. Native details/summary. --}}
@props(['label', 'open' => false])
<details @if($open) open @endif {{ $attributes->class('rounded-lg border border-border bg-surface px-3') }}>
    <summary class="min-h-11 cursor-pointer rounded-md py-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">{{ $label }}</summary>
    <div class="pb-3">{{ $slot }}</div>
</details>
