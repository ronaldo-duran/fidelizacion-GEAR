<?php

declare(strict_types=1);

use App\Http\Controllers\KitUiController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::view('/sin-conexion', 'sin-conexion')->name('sin-conexion');

Route::get('/_ui', KitUiController::class)->name('kit-ui');
