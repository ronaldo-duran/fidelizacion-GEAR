<?php

declare(strict_types=1);

use App\Enums\AlcanceApi;
use App\Http\Controllers\Api\V1\ComprasController;
use Illuminate\Support\Facades\Route;

/*
 * API pública para los comercios (E9-01), bajo /api/v1. En v1 solo se agregan
 * campos o rutas; quitar o cambiar uno es v2 (regla 13).
 *
 * Orden de los middleware: token, credencial activa con comercio y sede
 * activos, alcance de la operación y topes (regla 12).
 */
Route::middleware(['auth:sanctum', 'credencial.api'])->name('api.')->group(static function (): void {
    Route::middleware([AlcanceApi::ComprasRegistrar->middleware(), 'throttle:api'])->group(static function (): void {
        Route::post('compras', [ComprasController::class, 'store'])->middleware('idempotencia')->name('compras.store');
        Route::get('compras/{referenciaExterna}', [ComprasController::class, 'show'])->name('compras.show');
    });

    Route::post('compras/{referenciaExterna}/anulacion', [ComprasController::class, 'anular'])
        ->middleware([AlcanceApi::ComprasAnular->middleware(), 'throttle:api', 'idempotencia'])
        ->name('compras.anular');
});
