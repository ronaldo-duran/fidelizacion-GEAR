<?php

declare(strict_types=1);

use App\Api\RespuestaError;
use App\Http\Middleware\EnsureCredencialApi;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        // API pública para los comercios (E9-01): la versión va en la ruta.
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::group([], base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Los proxies de confianza salen de TRUSTED_PROXIES (AppServiceProvider): ninguno por defecto.
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'credencial.api' => EnsureCredencialApi::class,
            'idempotencia' => RequireIdempotencyKey::class,
        ]);

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

        // Todo error de la API en un solo formato, sin traza ni SQL (E9-01, regla 14).
        $exceptions->render(
            static fn (Throwable $error, Request $request) => $request->is('api', 'api/*')
                ? RespuestaError::para($error)
                : null,
        );
    })->create();
