<?php

use App\Http\Controllers\ApplicationPdfController;
use App\Http\Controllers\CvPageController;
use App\Http\Controllers\CvPdfController;
use App\Http\Controllers\CvPrivateDataController;
use App\Http\Controllers\CvPrivateLinkController;
use App\Http\Controllers\CvPrivateSignatureController;
use App\Http\Controllers\CvTokenExchangeController;
use App\Http\Controllers\SearchDocumentsController;
use App\Http\Middleware\ApplicationAccess;
use Illuminate\Support\Facades\Route;
use Statamic\View\View;

// Route::statamic('example', 'example-view', [
//    'title' => 'Example'
// ]);

Route::get('/sitemap.xml', [SearchDocumentsController::class, 'sitemap'])->name('sitemap');
Route::get('/llms.txt', [SearchDocumentsController::class, 'llms'])->name('llms');
Route::get('/llms-full.txt', [SearchDocumentsController::class, 'llmsFull'])->name('llms.full');

Route::get('/design-system', function () {
    abort_unless(app()->environment(['local', 'testing']) && config('design-system.enabled'), 404);

    return response(
        (new View)
            ->template('lab/index')
            ->layout('lab/layout')
            ->render()
    )->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->header('Cache-Control', 'private, no-store');
})->name('design-system');

Route::get('/cv/signature-example', [\App\Http\Controllers\ApplicationHandwrittenSignatureController::class, 'exampleImage'])->name('cv.signature-example');

Route::post('/cv/private-data', [CvPrivateDataController::class, 'reveal'])
    ->name('cv.private-data');
Route::post('/cv/private-signature', CvPrivateSignatureController::class)
    ->middleware('throttle:6,1')
    ->name('cv.private-signature');
Route::post('/cv/token-exchange', CvTokenExchangeController::class)
    ->middleware('throttle:12,1')
    ->name('cv.token-exchange');

Route::get('/cv/pdf', CvPdfController::class)
    ->middleware('throttle:6,1')
    ->name('cv.pdf');

Route::get('/cv/{profile}/pdf', CvPdfController::class)
    ->where('profile', '[a-z0-9-]+')
    ->middleware('throttle:6,1')
    ->name('cv.profile.pdf');

Route::post('/cp/cv/private-link/temporary', [CvPrivateLinkController::class, 'temporary'])
    ->middleware(['statamic.cp', 'statamic.cp.authenticated'])
    ->name('cv.private-link.temporary');
Route::post('/cp/cv/private-link/permanent', [CvPrivateLinkController::class, 'permanent'])
    ->middleware(['statamic.cp', 'statamic.cp.authenticated'])
    ->name('cv.private-link.permanent');
Route::delete('/cp/cv/private-link/permanent', [CvPrivateLinkController::class, 'revoke'])
    ->middleware(['statamic.cp', 'statamic.cp.authenticated'])
    ->name('cv.private-link.revoke');

Route::statamic('/projekte', 'projects/index', ['title' => 'Projekte']);
Route::statamic('/blog', 'posts/index', ['title' => 'Blog']);
Route::statamic('/publikationen', 'publications/index', ['title' => 'Publikationen']);
Route::statamic('/aktuell', 'now/index', ['title' => 'Aktuell']);
Route::get('/cv', [CvPageController::class, 'show'])->name('cv.show');
Route::get('/cv/{profile}', [CvPageController::class, 'profile'])
    ->where('profile', '[a-z0-9-]+')
    ->name('cv.profile');

Route::get('/cp/cv/private-link/status', [CvPrivateLinkController::class, 'status'])->middleware(['statamic.cp', 'statamic.cp.authenticated']);
Route::get('/cp/cv/private-link/qr', [CvPrivateLinkController::class, 'qr'])->middleware(['statamic.cp', 'statamic.cp.authenticated']);

Route::prefix('applications')->middleware(ApplicationAccess::class)->group(function () {
    Route::get('{id}/cv/pdf', [CvPageController::class, 'applicationPdf'])->whereUuid('id')->middleware('throttle:application-pdf')->name('applications.cv.pdf');
    Route::get('{id}/cv', [CvPageController::class, 'application'])->whereUuid('id')->name('applications.cv');
    Route::post('token-exchange', [ApplicationPdfController::class, 'exchange'])->middleware('throttle:application-exchange')->name('applications.exchange');
    Route::get('{id}/pdf', [ApplicationPdfController::class, 'recipient'])->whereUuid('id')->middleware('throttle:application-pdf')->name('applications.pdf');
    Route::get('{id}', [ApplicationPdfController::class, 'landing'])->whereUuid('id')->name('applications.access');
});
