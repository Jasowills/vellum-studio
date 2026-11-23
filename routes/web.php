<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\GenerationImageController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\UsageController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('/webhooks/pixelforge', WebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.pixelforge');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/studio', StudioController::class)->name('studio');

    Route::post('/studio/estimate', EstimateController::class)
        ->middleware('throttle:estimates')
        ->name('studio.estimate');

    Route::post('/generations', [GenerationController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('generations.store');

    Route::get('/generations/{generation}', [GenerationController::class, 'show'])
        ->name('generations.show');

    Route::get('/generations/{generation}/image', GenerationImageController::class)
        ->middleware('throttle:images')
        ->name('generations.image');

    Route::get('/library', LibraryController::class)->name('library');

    Route::get('/usage', UsageController::class)->name('usage');

    Route::redirect('/dashboard', '/studio')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/report', ReportController::class)
            ->middleware('throttle:admin-report')
            ->name('admin.report');
    });

require __DIR__.'/settings.php';
