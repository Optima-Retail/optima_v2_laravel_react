<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned API entrypoint. Keep version files under routes/api/.
|
*/

Route::prefix('v3')
    ->name('api.v3.')
    ->group(base_path('routes/api/v3.php'));
