<?php

declare(strict_types=1);

use App\Http\Controllers\AssignmentSubmissionController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LearnerHomeController;
use App\Http\Controllers\LiveJoinController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::get('/login', [PageController::class, 'login'])->name('login.page');
Route::get('/register', [PageController::class, 'register'])->name('register.page');
Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('password.request');
Route::get('/reset-password/{token}', [PageController::class, 'resetPassword'])->name('password.reset');

Route::middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class])->group(function (): void {
    Route::get('/home', [PageController::class, 'home'])->name('home');
    Route::get('/my', [LearnerHomeController::class, 'dashboard'])->name('learner.dashboard');
    Route::get('/parent', ParentDashboardController::class)->name('parent.dashboard');
    Route::get('/my/courses', [LearnerHomeController::class, 'courses'])->name('learner.courses');
    Route::get('/preview/{course}/{item}', PreviewController::class)->whereNumber(['course', 'item'])->name('learn.preview');
    Route::get('/learn/{course}', [LearnController::class, 'course'])->whereNumber('course')->name('learn.course');
    Route::get('/learn/{course}/{item}', [LearnController::class, 'show'])->whereNumber(['course', 'item'])->name('learn.show');
    Route::post('/learn/{course}/{item}/join', LiveJoinController::class)->whereNumber(['course', 'item'])->middleware('throttle:progress')->name('learn.join');
    Route::post('/learn/{course}/{item}/quiz/start', [QuizAttemptController::class, 'start'])->whereNumber(['course', 'item'])->middleware('throttle:progress')->name('quiz.start');
    Route::get('/learn/{course}/{item}/quiz/{attempt}', [QuizAttemptController::class, 'show'])->whereNumber(['course', 'item', 'attempt'])->name('quiz.attempt');
    Route::post('/learn/{course}/{item}/quiz/{attempt}/save', [QuizAttemptController::class, 'save'])->whereNumber(['course', 'item', 'attempt'])->middleware('throttle:progress')->name('quiz.save');
    Route::post('/learn/{course}/{item}/quiz/{attempt}/submit', [QuizAttemptController::class, 'submit'])->whereNumber(['course', 'item', 'attempt'])->middleware('throttle:progress')->name('quiz.submit');
    Route::post('/learn/{course}/{item}/assignment/save', [AssignmentSubmissionController::class, 'save'])->whereNumber(['course', 'item'])->middleware('throttle:progress')->name('assignment.save');
    Route::post('/learn/{course}/{item}/assignment/submit', [AssignmentSubmissionController::class, 'submit'])->whereNumber(['course', 'item'])->middleware('throttle:progress')->name('assignment.submit');
    Route::get('/assignment/submission-files/{file}', [AssignmentSubmissionController::class, 'download'])->whereNumber('file')->name('assignment.file');
    Route::get('/assignment/attachments/{attachment}', [AssignmentSubmissionController::class, 'attachment'])->whereNumber('attachment')->name('assignment.attachment');
    Route::post('/learn/{course}/{item}/progress', [ProgressController::class, 'heartbeat'])->whereNumber(['course', 'item'])->middleware('throttle:progress')->name('learn.progress');
    Route::post('/learn/{course}/{item}/complete', [ProgressController::class, 'complete'])->whereNumber(['course', 'item'])->middleware('throttle:progress')->name('learn.complete');
    Route::get('/my/certificates/{certificate}', [CertificateController::class, 'download'])->whereNumber('certificate')->middleware('throttle:certificate-download')->name('certificates.download');
    Route::get('/email/verify', [PageController::class, 'verifyNotice'])->name('verification.notice');
    Route::get('/profile', [PageController::class, 'profile'])->name('profile.page');
    Route::get('/family', [PageController::class, 'family'])->name('family');
    Route::get('/link', [PageController::class, 'link'])->name('link');
});

// Public certificate verification: no account, safe fields only, throttled per address.
Route::get('/verify/{token}', [CertificateController::class, 'verify'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:certificate-verify')->name('certificates.verify');

// Unmatched URLs still pass through the web middleware so the branded 404 follows the selected locale.
Route::fallback(static fn () => abort(404));
