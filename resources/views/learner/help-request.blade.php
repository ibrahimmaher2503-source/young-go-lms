@php
    $zone = auth()->user()?->timezone;
    $zone = is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : config('app.timezone');
@endphp
<x-layouts.learner :title="__('help.thread.title')">
    <div class="mx-auto max-w-3xl">
        <x-learning.help-request-thread :help-request="$helpRequest" :messages="$messages" :private="$private" :can-reply="$canReply" :zone="$zone" />
    </div>
</x-layouts.learner>
