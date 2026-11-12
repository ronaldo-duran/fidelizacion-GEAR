<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::group([], base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Los proxies de confianza salen de TRUSTED_PROXIES (AppServiceProvider): ninguno por defecto.
        $middleware->append(SecurityHeaders::class);

        // Rutas del cliente y de la caja (E4-02): el panel tiene su propio ingreso (Filament).
        $middleware->redirectGuestsTo(
            fn (Request $request) => route($request->is('caja', 'caja/*') ? 'caja.ingreso' : 'cliente.ingreso'),
        );
        $middleware->redirectUsersTo(fn () => route('cliente.inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
