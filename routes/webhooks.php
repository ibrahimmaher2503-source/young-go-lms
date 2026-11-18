<?php

declare(strict_types=1);

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// T-P8-03: server-to-server callbacks. Registered outside the `web` group on purpose: no session, no cookies, no CSRF
// token and no auth; the signature checked inside the action is the only credential. Rate limited per IP.
Route::post((string) config('payments.callback_path', '/webhooks/paymob'), PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.paymob');
