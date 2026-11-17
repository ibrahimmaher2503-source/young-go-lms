{{-- Props: action, method (POST default), success (redirect url), chain (list of urls), result (id of result element), reload, reset, message. Posts to the JSON endpoints via resources/js/forms.js. --}}
@props(['action', 'method' => 'POST', 'success' => null, 'chain' => null, 'result' => null, 'reload' => false, 'reset' => false, 'message' => null, 'files' => false])
<form method="post" action="{{ $action }}" data-ajax novalidate
    @if($files) enctype="multipart/form-data" @endif
    @if($success) data-success-url="{{ $success }}" @endif
    @if($chain) data-chain='@json($chain)' @endif
    @if($result) data-result-target="{{ $result }}" @endif
    @if($reload) data-reload @endif
    @if($reset) data-reset @endif
    @if($message) data-success-message="{{ $message }}" @endif
    {{ $attributes->class('space-y-4') }}>
    @csrf
    @if(strtoupper($method) !== 'POST') @method($method) @endif
    <div data-form-status tabindex="-1" hidden></div>
    {{ $slot }}
</form>
