<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V3\Config\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('users')->name('users.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('index');

    Route::post('/', [UserController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('store');

    Route::get('/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view')
        ->name('show');

    Route::put('/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('update');

    Route::delete('/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('destroy');
});
