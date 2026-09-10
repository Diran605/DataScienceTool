<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CleanController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\RunStepController;
use App\Http\Controllers\SchemaContractController;
use App\Http\Controllers\ValidateController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->to(auth()->check() ? '/runs' : '/login');
});

Route::get('/dashboard', function () {
    return redirect()->to('/runs');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Runs
    Route::resource('runs', RunController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::get('runs/{run}/steps/{step}', [RunStepController::class, 'show'])->name('run-steps.show');

    // Clean / Validate / Export actions on runs
    Route::get('runs/{run}/clean', [CleanController::class, 'edit'])->name('runs.clean');
    Route::put('runs/{run}/clean', [CleanController::class, 'update'])->name('runs.clean.update');
    Route::get('runs/{run}/validate', [ValidateController::class, 'create'])->name('runs.validate');
    Route::post('runs/{run}/validate', [ValidateController::class, 'store'])->name('runs.validate.store');
    Route::get('runs/{run}/export', [ExportController::class, 'create'])->name('runs.export');
    Route::post('runs/{run}/export', [ExportController::class, 'store'])->name('runs.export.store');

    // Schema Contracts
    Route::resource('contracts', SchemaContractController::class)->except(['show']);

    // Deliveries
    Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::get('deliveries/{delivery}/download', [DeliveryController::class, 'download'])->name('deliveries.download');
});

// Admin routes — superadmin only
Route::middleware(['auth', 'verified', 'superadmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/contracts', [Admin\ContractUserController::class, 'store'])->name('users.contracts.store');
        Route::delete('users/{user}/contracts/{contract}', [Admin\ContractUserController::class, 'destroy'])->name('users.contracts.destroy');
    });

require __DIR__.'/auth.php';
