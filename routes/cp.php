<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationHandwrittenSignatureController;
use App\Http\Controllers\ApplicationPdfController;
use App\Http\Controllers\ApplicationSignatureController;
use App\Http\Controllers\SearchAgentOptimizationController;
use App\Http\Middleware\ApplicationAccess;
use Illuminate\Support\Facades\Route;

Route::get('/search-agent-optimization', [SearchAgentOptimizationController::class, 'index'])
    ->name('search-agent-optimization.index');
Route::post('/search-agent-optimization', [SearchAgentOptimizationController::class, 'update'])
    ->name('search-agent-optimization.update');

Route::post('/applications/{id}/reset-date', [ApplicationController::class, 'resetDate'])->name('applications.reset-date');
Route::get('/applications/handwritten-signature/{source?}', [ApplicationHandwrittenSignatureController::class, 'show'])->where('source', 'example')->name('applications.handwritten-signature.show');
Route::post('/applications/handwritten-signature/{source?}', [ApplicationHandwrittenSignatureController::class, 'store'])->middleware('throttle:application-pdf')->where('source', 'example')->name('applications.handwritten-signature.store');
Route::delete('/applications/handwritten-signature/{source?}', [ApplicationHandwrittenSignatureController::class, 'destroy'])->where('source', 'example')->name('applications.handwritten-signature.destroy');
Route::get('/applications/{id}/cv.pdf', [ApplicationController::class, 'cv'])->name('applications.cv');
Route::get('/applications/{id}/summary', [ApplicationController::class, 'summary'])->name('applications.summary');
Route::get('/applications/{id}/cv-profile', [ApplicationController::class, 'cvProfile'])->name('applications.cv-profile');

Route::middleware(ApplicationAccess::class)->group(function () {
    Route::get('/applications/{id}/signatures/{action}', [ApplicationSignatureController::class, 'handle'])->whereIn('action', ['status', 'download'])->name('applications.signatures.read');
    Route::post('/applications/{id}/signatures/{action}', [ApplicationSignatureController::class, 'handle'])->whereIn('action', ['prepare', 'import', 'activate', 'unsigned', 'sandbox'])->middleware('throttle:application-pdf')->name('applications.signatures.write');
    Route::get('/applications/{id}/preview.pdf', [ApplicationPdfController::class, 'cp'])->middleware('throttle:application-pdf')->name('applications.preview');
    Route::get('/applications/{id}/export.pdf', [ApplicationPdfController::class, 'cp'])->middleware('throttle:application-pdf')->name('applications.export');
    Route::get('/applications/{id}/recipient-grants', [ApplicationPdfController::class, 'grants'])->name('applications.recipient-grants');
    Route::post('/applications/{id}/recipient-grants', [ApplicationPdfController::class, 'issueGrant'])->name('applications.recipient-grants.issue');
    Route::delete('/applications/{id}/recipient-grants/{grantId}', [ApplicationPdfController::class, 'revokeGrant'])->name('applications.recipient-grants.revoke');
    Route::delete('/applications/{id}/recipient-grants', [ApplicationPdfController::class, 'revokeAllGrants'])->name('applications.recipient-grants.revoke-all');
    // Keep the former endpoints as non-rotating compatibility aliases.
    Route::post('/applications/{id}/recipient-link', [ApplicationPdfController::class, 'issueLegacyGrant'])->name('applications.recipient-link');
    Route::delete('/applications/{id}/recipient-link', [ApplicationPdfController::class, 'revokeAllGrants'])->name('applications.recipient-link.revoke');
});
