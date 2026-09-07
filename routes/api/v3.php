<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V3\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V3 Routes
|--------------------------------------------------------------------------
|
| Prefix is applied in routes/api.php as /api/v3.
| Add new domain route files under routes/api/v3/ and require them here.
|
*/

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    });
});

Route::prefix('config')->name('config.')->group(function (): void {
    require __DIR__.'/v3/config/users.php';
});

require __DIR__.'/v3/companies.php';
