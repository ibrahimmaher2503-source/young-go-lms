{{-- Props: helpRequest (HelpRequest with lesson and course), messages (replies to show), private (false in parent context: no question/answer),
     canReply (learner may answer now), zone (display time zone). The learner's help request thread (T-P7-04): status, what was asked, the replies. --}}
@props(['helpRequest', 'messages', 'private' => true, 'canReply' => false, 'zone' => null])
@php($zone = $zone ?: config('app.timezone'))
<section class="space-y-4" data-help-thread data-help-status="{{ $helpRequest->status->value }}" aria-labelledby="help-thread-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 id="help-thread-title" class="text-2xl font-bold">{{ __('help.thread.title') }}</h1>
        <x-ui.badge :tone="in_array($helpRequest->status->tone(), ['gray', 'primary'], true) ? 'neutral' : $helpRequest->status->tone()" data-help-status-badge>{{ $helpRequest->status->label() }}</x-ui.badge>
    </div>
    <p class="text-muted">{{ __('help.thread.about', ['lesson' => $helpRequest->lesson->title, 'course' => $helpRequest->course->title]) }}</p>

    @if($private)
        <x-ui.card class="space-y-3" data-help-context>
            <div><h2 class="text-sm font-semibold text-muted">{{ __('help.thread.your_question') }}</h2><p class="break-words whitespace-pre-line" dir="auto">{{ $helpRequest->question }}</p></div>
            <div><h2 class="text-sm font-semibold text-muted">{{ __('help.thread.ai_answer') }}</h2><p class="break-words whitespace-pre-line" dir="auto">{{ $helpRequest->ai_answer }}</p></div>
        </x-ui.card>
    @endif

    <ol class="space-y-3" data-help-messages aria-label="{{ __('help.thread.replies') }}">
        @forelse($messages as $message)
            <li class="space-y-1 rounded-card border border-border p-3 {{ $message->role === 'instructor' ? 'bg-assistant' : 'bg-surface' }}" data-help-message="{{ $message->role }}">
                <p class="text-sm text-muted">{{ $message->role === 'instructor' ? __('help.thread.instructor') : __('help.thread.you') }} ·
                    <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->copy()->timezone($zone)->isoFormat('LLL') }}</time></p>
                <p class="break-words whitespace-pre-line" dir="auto">{{ $message->body }}</p>
            </li>
        @empty
            <li class="text-muted" data-help-empty>{{ __('help.thread.no_replies') }}</li>
        @endforelse
    </ol>

    @if(session('help_notice'))<x-ui.alert tone="success" role="status">{{ session('help_notice') }}</x-ui.alert>@endif

    @if($canReply)
        <form method="POST" action="{{ route('help.reply', $helpRequest) }}" class="space-y-2" data-help-reply-form>
            @csrf
            <label for="help-reply-body" class="font-semibold">{{ __('help.thread.your_reply') }}</label>
            <x-ui.textarea id="help-reply-body" name="body" rows="4" dir="auto" required maxlength="{{ (int) config('ai.help.reply_max_chars', 2000) }}" :invalid="$errors->has('body')" aria-describedby="{{ $errors->has('body') ? 'help-reply-error' : '' }}">{{ old('body') }}</x-ui.textarea>
            @error('body')<p id="help-reply-error" class="text-danger" role="alert">{{ $message }}</p>@enderror
            <x-ui.button type="submit">{{ __('help.thread.send') }}</x-ui.button>
        </form>
    @elseif($private && $helpRequest->status->isOpen())
        <p class="text-muted" data-help-wait>{{ __('help.thread.waiting_instructor') }}</p>
    @elseif($helpRequest->status->isOpen() === false)
        <p class="text-muted" data-help-closed>{{ __('help.thread.closed') }}</p>
    @endif
</section>
