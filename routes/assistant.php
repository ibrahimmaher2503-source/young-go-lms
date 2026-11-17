<?php

declare(strict_types=1);

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AssistantFeedbackController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

// Lesson assistant (T-P7-01, AI spec 17). The lesson is resolved here; the student comes from the session.
Route::middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class])->prefix('api/v1')->group(function (): void {
    Route::get('/lessons/{lesson}/assistant', [AssistantController::class, 'show'])->whereNumber('lesson')->middleware('throttle:assistant-status')->name('assistant.show');
    Route::post('/lessons/{lesson}/assistant/messages', [AssistantController::class, 'store'])->whereNumber('lesson')->middleware('throttle:assistant-ask')->name('assistant.ask');
    // T-P7-04: understood / still not understood on one answer; the second creates the help request.
    Route::post('/lessons/{lesson}/assistant/feedback', [AssistantFeedbackController::class, 'store'])->whereNumber('lesson')->middleware('throttle:assistant-status')->name('assistant.feedback');
});
