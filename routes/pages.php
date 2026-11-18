<?php

declare(strict_types=1);

use App\Http\Controllers\AssignmentSubmissionController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CourseReviewController;
use App\Http\Controllers\FakeGatewayController;
use App\Http\Controllers\HelpRequestController;
use App\Http\Controllers\InstructorStatementController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LearnerHomeController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\LiveJoinController;
use App\Http\Controllers\ManualPaymentProofController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupportViewAsController;
use App\Http\Controllers\WishlistController;
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
    // T-P9-10: support view-as (read-only, audited, time-limited). The session itself is checked in the controller and by EnforceViewAsReadOnly.
    Route::prefix('support/view-as')->name('support.view-as.')->group(function (): void {
        Route::get('/', [SupportViewAsController::class, 'home'])->name('home');
        Route::get('/courses/{course}', [SupportViewAsController::class, 'course'])->whereNumber('course')->name('course');
        Route::post('/stop', [SupportViewAsController::class, 'stop'])->name('stop');
    });
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
    Route::get('/my/help/{helpRequest}', [HelpRequestController::class, 'show'])->whereNumber('helpRequest')->name('help.show');
    Route::post('/my/help/{helpRequest}/replies', [HelpRequestController::class, 'reply'])->whereNumber('helpRequest')->middleware('throttle:progress')->name('help.reply');
    Route::get('/my/orders/{order}/payment-proof', [ManualPaymentProofController::class, 'create'])->whereNumber('order')->name('manual-payments.create');
    Route::post('/my/orders/{order}/payment-proof', [ManualPaymentProofController::class, 'store'])->whereNumber('order')->middleware('throttle:progress')->name('manual-payments.store');
    Route::get('/payments/proofs/{proof}/file', [ManualPaymentProofController::class, 'file'])->whereNumber('proof')->middleware('throttle:certificate-download')->name('manual-payments.file');
    // T-P8-12: learner checkout. GET routes never write; the coupon code is rate limited per account.
    Route::get('/checkout/return', [CheckoutController::class, 'return'])->name('checkout.return');
    Route::get('/checkout/{type}/{id}', [CheckoutController::class, 'product'])->whereIn('type', ['course', 'bundle', 'plan'])->whereNumber('id')->name('checkout.product');
    Route::post('/checkout/start', [CheckoutController::class, 'start'])->middleware('throttle:20,1')->name('checkout.start');
    Route::get('/checkout/orders/{order}', [CheckoutController::class, 'order'])->whereNumber('order')->name('checkout.order');
    Route::post('/checkout/orders/{order}/coupon', [CheckoutController::class, 'applyCoupon'])->whereNumber('order')->middleware('throttle:checkout-coupon')->name('checkout.coupon.apply');
    Route::delete('/checkout/orders/{order}/coupon', [CheckoutController::class, 'removeCoupon'])->whereNumber('order')->middleware('throttle:20,1')->name('checkout.coupon.remove');
    Route::post('/checkout/orders/{order}/pay', [CheckoutController::class, 'pay'])->whereNumber('order')->middleware('throttle:10,1')->name('checkout.pay');
    // T-P8-08/T-P8-12: the account's own subscriptions (learner or payer). Renewal is manual and only creates a pending order.
    Route::get('/my/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/my/subscription/{subscription}/renew', [SubscriptionController::class, 'renew'])->whereNumber('subscription')->middleware('throttle:10,1')->name('subscription.renew');
    Route::post('/my/subscription/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->whereNumber('subscription')->middleware('throttle:10,1')->name('subscription.cancel');
    Route::get('/my/payments', [PaymentHistoryController::class, 'index'])->name('payments.history');
    Route::get('/my/payments/{order}/receipt', [PaymentHistoryController::class, 'receipt'])->whereNumber('order')->middleware('throttle:certificate-download')->name('payments.receipt');
    Route::get('/my/payments/{order}/refunds/{refund}/receipt', [PaymentHistoryController::class, 'refundReceipt'])->whereNumber(['order', 'refund'])->middleware('throttle:certificate-download')->name('payments.refund-receipt');
    // T-P8-10: instructor statement PDF (own statements; finance with ledger.view).
    Route::get('/my/statements/{statement}/pdf', [InstructorStatementController::class, 'pdf'])->whereNumber('statement')->middleware('throttle:certificate-download')->name('statements.pdf');
    // T-P9-06: staff report CSV export (scoped and audited in the action; unreadable report = 403).
    Route::get('/reports/{report}/export', ReportExportController::class)->middleware('throttle:20,1')->name('reports.export');
    Route::get('/my/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/my/wishlist/{course}', [WishlistController::class, 'toggle'])->whereNumber('course')->middleware('throttle:30,1')->name('wishlist.toggle');
    Route::post('/courses/{course}/reviews', [CourseReviewController::class, 'store'])->whereNumber('course')->middleware('throttle:6,1')->name('reviews.store');
    Route::get('/email/verify', [PageController::class, 'verifyNotice'])->name('verification.notice');
    Route::get('/profile', [PageController::class, 'profile'])->name('profile.page');
    Route::get('/family', [PageController::class, 'family'])->name('family');
    Route::get('/link', [PageController::class, 'link'])->name('link');
});

// Demo stand-in for the fake gateway's hosted page (the controller answers 404 outside local and testing).
Route::get('/fake-gateway/{reference}', [FakeGatewayController::class, 'show'])->name('fake-gateway.show');
Route::post('/fake-gateway/{reference}', [FakeGatewayController::class, 'complete'])->middleware('throttle:20,1')->name('fake-gateway.complete');

// Public certificate verification: no account, safe fields only, throttled per address.
Route::get('/verify/{token}', [CertificateController::class, 'verify'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:certificate-verify')->name('certificates.verify');

// Unmatched URLs still pass through the web middleware so the branded 404 follows the selected locale.
Route::fallback(LegacyRedirectController::class);
