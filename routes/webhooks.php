<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\PagosController;
use Illuminate\Support\Facades\Route;

// Webhooks de terceros (E6-01): sin sesión, cookies ni CSRF; los carga
// bootstrap/app.php fuera del grupo `web`.
Route::post('/webhooks/pagos/{pasarela}', PagosController::class)
    ->where('pasarela', '[a-z]+')
    ->middleware('throttle:webhooks')
    ->name('webhooks.pagos');
