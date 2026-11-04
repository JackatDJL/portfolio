<?php

use App\Http\Controllers\SearchAgentOptimizationController;
use Illuminate\Support\Facades\Route;

Route::get('/search-agent-optimization', [SearchAgentOptimizationController::class, 'index'])
    ->name('search-agent-optimization.index');
Route::post('/search-agent-optimization', [SearchAgentOptimizationController::class, 'update'])
    ->name('search-agent-optimization.update');
