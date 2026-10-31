<?php

declare(strict_types=1);

use App\Http\Controllers\Cajero\LogoutController as CajeroLogoutController;
use App\Http\Controllers\Cliente\LogoutController;
use App\Http\Controllers\Cliente\RegistroCajaController;
use App\Http\Controllers\KitUiController;
use App\Http\Middleware\EnsureCajero;
use App\Livewire\Cajero\Caja;
use App\Livewire\Cajero\Ingreso as CajeroIngreso;
use App\Livewire\Cajero\ValidarBeneficio;
use App\Livewire\Cliente\Ingreso;
use App\Livewire\Cliente\RecuperarPin;
use App\Livewire\Cliente\Registro;
use App\Livewire\Cliente\VerificarRegistro;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::view('/sin-conexion', 'sin-conexion')->name('sin-conexion');

Route::get('/_ui', KitUiController::class)->name('kit-ui');

// PWA del cliente (E8-02). Guard propio, separado del panel.
Route::name('cliente.')->group(function (): void {
    Route::middleware('guest:cliente')->group(function (): void {
        Route::get('/registro', Registro::class)->name('registro');
        Route::get('/registro/verificar', VerificarRegistro::class)->name('registro.verificar');
        Route::get('/registro/caja', RegistroCajaController::class)->middleware('signed')->name('registro.caja');
        Route::get('/ingresar', Ingreso::class)->name('ingreso');
        Route::get('/recuperar-pin', RecuperarPin::class)->name('recuperar-pin');
    });

    Route::middleware(['auth:cliente', 'auth.session'])->group(function (): void {
        // TODO(E8-03): el inicio del cliente reemplaza esta vista provisional.
        Route::view('/inicio', 'cliente.inicio')->name('inicio');
        Route::post('/salir', LogoutController::class)->name('salir');
    });
});

// Caja del comercio (E4-02): la cuenta del sistema (guard web), fuera del panel.
Route::prefix('caja')->name('caja.')->group(function (): void {
    Route::get('/ingresar', CajeroIngreso::class)->name('ingreso');

    Route::middleware(['auth:web', EnsureCajero::class])->group(function (): void {
        Route::get('/', Caja::class)->name('inicio');
        Route::get('/beneficios', ValidarBeneficio::class)->name('beneficios');
        Route::post('/salir', CajeroLogoutController::class)->name('salir');
    });
});
