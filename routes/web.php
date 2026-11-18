<?php

declare(strict_types=1);

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InstructorApplicationController;
use App\Http\Controllers\MediaPlaybackController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicCategoryController;
use App\Http\Controllers\PublicCourseController;
use App\Http\Controllers\PublicCoursePreviewController;
use App\Http\Controllers\PublicInstructorController;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CmsController::class, 'home'])->name('cms.home');
Route::get('/courses', PublicCatalogController::class)->name('catalog.index');
Route::get('/courses/{course}', PublicCourseController::class)->name('catalog.show');
Route::get('/courses/{course}/preview/{item}', PublicCoursePreviewController::class)->whereNumber('item')->name('catalog.preview');
Route::get('/pages/{slug}', [CmsController::class, 'show'])->name('cms.show');
Route::get('/categories/{category}', PublicCategoryController::class)->name('categories.show');
Route::get('/instructors/{instructor}', PublicInstructorController::class)->name('instructors.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show');
// T-P2-08: public lead capture. Spam: honeypot field + per-address throttles; unsubscribe is a signed link, no login.
Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
// T-P9-01: "Teach with Young Go". Public page; applying needs a signed-in account; ID document download is staff-only.
Route::get('/teach', [InstructorApplicationController::class, 'show'])->name('instructors.teach');
Route::post('/teach', [InstructorApplicationController::class, 'store'])
    ->middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class, 'throttle:instructor-application'])->name('instructors.apply');
Route::get('/admin/instructor-applications/{application}/id-document', [InstructorApplicationController::class, 'document'])
    ->whereNumber('application')->middleware(['auth', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class, 'can:instructor.manage', 'throttle:certificate-download'])->name('instructor-applications.document');
Route::get('/cookies', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/cookies', [ConsentController::class, 'store'])->middleware('throttle:30,1')->name('consent.store');
Route::get('/newsletter', [NewsletterController::class, 'show'])->name('newsletter.show');
Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:newsletter')->name('newsletter.store');
Route::get('/newsletter/unsubscribe/{subscriber}', [NewsletterController::class, 'unsubscribe'])
    ->whereNumber('subscriber')->middleware(['signed', 'throttle:newsletter-unsubscribe'])->name('newsletter.unsubscribe');
Route::get('/admin/cms-preview/{page}', [CmsController::class, 'preview'])
    ->middleware(['auth', 'signed', EnsureActiveAccount::class, 'auth.session', EnforceTwoFactor::class, 'can:content.page.manage'])
    ->name('cms.preview');

Route::get('/_design/tokens', function (Request $request) {
    abort_unless(app()->environment('local', 'staging') && config('design.token_preview'), 404);

    $locale = $request->query('locale') === 'ar' ? 'ar' : 'en';
    $theme = $request->query('theme') === 'dark' ? 'dark' : 'light';
    app()->setLocale($locale);

    return response()->view('design.tokens', compact('locale', 'theme'))
        ->header('Cache-Control', 'private, no-store');
})->name('design.tokens');

// Private lesson files (resources, curriculum PDFs): signed, short-lived, tied to the signed-in account, access rechecked.
Route::get('/files/{type}/{id}', PrivateFileController::class)->whereNumber('id')->middleware(['auth', 'signed'])->name('files.download');

// Protected media: only reachable through a signed, short-lived link produced by a media provider.
Route::get('/media/{asset}', MediaPlaybackController::class)->whereNumber('asset')->middleware(['auth', 'signed'])->name('media.play');
