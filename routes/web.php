<?php

use App\Http\Controllers\CvPdfController;
use App\Http\Controllers\CvPrivateDataController;
use App\Http\Controllers\CvPrivateLinkController;
use Illuminate\Support\Facades\Route;
use Statamic\View\View;

// Route::statamic('example', 'example-view', [
//    'title' => 'Example'
// ]);

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

Route::post('/cv/private-data', [CvPrivateDataController::class, 'reveal'])
    ->name('cv.private-data');

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
Route::statamic('/cv', 'cv/show', ['title' => 'Lebenslauf']);
Route::statamic('/cv/{profile}', 'cv/profile');

Route::get('/cp/cv/private-link/status', [CvPrivateLinkController::class, 'status'])->middleware(['statamic.cp', 'statamic.cp.authenticated']);
