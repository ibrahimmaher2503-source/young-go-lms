{{-- Props: steps (translated labels), current (one-based position). --}}
@props(['steps', 'current' => 1])
<ol aria-label="{{ __('components.steps') }}" {{ $attributes->class('flex flex-wrap gap-x-4 gap-y-2') }}>
    @foreach($steps as $step)
        <li @if($loop->iteration === $current) aria-current="step" @endif class="flex min-h-11 items-center gap-2 aria-[current=step]:font-semibold">
            <x-ui.badge :tone="$loop->iteration < $current ? 'success' : 'neutral'"><bdi dir="ltr">{{ $loop->iteration }}</bdi></x-ui.badge>
            {{ $step }}
        </li>
    @endforeach
</ol>
