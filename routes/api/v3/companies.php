<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V3\CompanyController;
use App\Http\Controllers\Api\V3\CompanyRelationshipController;
use App\Http\Controllers\Api\V3\EstablishmentController;
use App\Http\Controllers\Api\V3\MeCompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me/companies', [MeCompanyController::class, 'index'])->name('me.companies');
    Route::post('/me/company/switch', [MeCompanyController::class, 'switch'])->name('me.company.switch');

    Route::prefix('companies')->name('companies.')->group(function (): void {
        Route::get('/', [CompanyController::class, 'index'])->middleware('permission:companies.view')->name('index');
        Route::post('/', [CompanyController::class, 'store'])->middleware('permission:companies.create')->name('store');
        Route::get('/{company}', [CompanyController::class, 'show'])->middleware('permission:companies.view')->name('show');
        Route::put('/{company}', [CompanyController::class, 'update'])->middleware('permission:companies.update')->name('update');
        Route::delete('/{company}', [CompanyController::class, 'destroy'])->middleware('permission:companies.delete')->name('destroy');
    });

    Route::middleware('company.context')->group(function (): void {
        Route::prefix('relationships')->name('relationships.')->group(function (): void {
            Route::get('/', [CompanyRelationshipController::class, 'index'])->middleware('permission:company_relationships.view')->name('index');
            Route::post('/', [CompanyRelationshipController::class, 'store'])->middleware('permission:company_relationships.create')->name('store');
            Route::get('/{relationship}', [CompanyRelationshipController::class, 'show'])->middleware('permission:company_relationships.view')->name('show');
            Route::put('/{relationship}', [CompanyRelationshipController::class, 'update'])->middleware('permission:company_relationships.update')->name('update');
            Route::delete('/{relationship}', [CompanyRelationshipController::class, 'destroy'])->middleware('permission:company_relationships.delete')->name('destroy');
        });

        Route::prefix('establishments')->name('establishments.')->group(function (): void {
            Route::get('/', [EstablishmentController::class, 'index'])->middleware('permission:establishments.view')->name('index');
            Route::post('/', [EstablishmentController::class, 'store'])->middleware('permission:establishments.create')->name('store');
            Route::get('/{establishment}', [EstablishmentController::class, 'show'])->middleware('permission:establishments.view')->name('show');
            Route::put('/{establishment}', [EstablishmentController::class, 'update'])->middleware('permission:establishments.update')->name('update');
            Route::delete('/{establishment}', [EstablishmentController::class, 'destroy'])->middleware('permission:establishments.delete')->name('destroy');
        });
    });
});
