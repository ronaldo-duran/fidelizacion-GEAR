<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Api\ErrorApi;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Después de `auth:sanctum`: el token debe ser de una credencial de la API
 * activa, con su comercio y su sede activos (E9-01, reglas 1 y 5). Una
 * credencial de un comercio o de una sede desactivados responde 403 sin
 * decir nada del token.
 */
class EnsureCredencialApi
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->esIntegracion() || ! $user->activo || $user->sede_id === null) {
            throw new AuthorizationException('El token no es de una credencial de la API.');
        }

        $user->loadMissing('sede.comercio');
        $sede = $user->sede;

        if ($sede?->comercio === null || ! $sede->comercio->activo || $sede->comercio_id !== $user->comercio_id) {
            throw new ErrorApi(403, 'comercio_inactivo', 'El comercio de esta credencial no está activo.');
        }

        if (! $sede->activa) {
            throw new ErrorApi(403, 'sede_inactiva', 'La sede de esta credencial no está activa.');
        }

        return $next($request);
    }
}
