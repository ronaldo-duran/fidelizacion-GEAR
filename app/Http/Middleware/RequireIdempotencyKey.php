<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Api\ErrorApi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Todo POST de la API exige `Idempotency-Key` con un UUID (E9-01, regla 6 y
 * decisión 3). Es solo la puerta del 400, antes de tocar la base: lo que
 * evita duplicados es la restricción única de cada tabla (regla dura 3.7).
 */
class RequireIdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $llave = $request->header(self::HEADER);

        if (! is_string($llave) || trim($llave) === '') {
            throw new ErrorApi(400, 'idempotency_key_requerida', 'Falta el header Idempotency-Key.');
        }

        if (! Str::isUuid(trim($llave))) {
            throw new ErrorApi(400, 'idempotency_key_invalida', 'El header Idempotency-Key debe ser un UUID.');
        }

        return $next($request);
    }

    /**
     * La llave de la petición, en minúsculas para que dos escrituras del mismo
     * UUID sean la misma llave en los dos motores.
     */
    public static function llave(Request $request): string
    {
        return mb_strtolower(trim((string) $request->header(self::HEADER)));
    }
}
