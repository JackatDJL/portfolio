<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationPdfController;
use App\Http\Controllers\SearchAgentOptimizationController;
use App\Http\Middleware\ApplicationAccess;
use Illuminate\Support\Facades\Route;

Route::get('/search-agent-optimization', [SearchAgentOptimizationController::class, 'index'])
    ->name('search-agent-optimization.index');
Route::post('/search-agent-optimization', [SearchAgentOptimizationController::class, 'update'])
    ->name('search-agent-optimization.update');

Route::post('/applications/{id}/reset-date', [ApplicationController::class, 'resetDate'])->name('applications.reset-date');
Route::get('/applications/{id}/cv.pdf', [ApplicationController::class, 'cv'])->name('applications.cv');

Route::middleware(ApplicationAccess::class)->group(function () {
    Route::get('/applications/{id}/preview.pdf', [ApplicationPdfController::class, 'cp'])->middleware('throttle:application-pdf')->name('applications.preview');
    Route::get('/applications/{id}/export.pdf', [ApplicationPdfController::class, 'cp'])->middleware('throttle:application-pdf')->name('applications.export');
    Route::post('/applications/{id}/recipient-link', [ApplicationPdfController::class, 'issue'])->name('applications.recipient-link');
    Route::delete('/applications/{id}/recipient-link', [ApplicationPdfController::class, 'revoke'])->name('applications.recipient-link.revoke');
});
