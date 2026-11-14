<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

/**
 * Base de los controladores de la API pública (E9-01). Son delgados: validan
 * con su Form Request, llaman a una Action y responden `{"data": …}` con lo
 * que arma la clase `*Json` del recurso.
 */
abstract class ApiController
{
    /**
     * @param  array<array-key, mixed>  $datos
     */
    protected static function datos(array $datos, int $estado = 200): JsonResponse
    {
        return new JsonResponse(['data' => $datos], $estado);
    }

    /**
     * Una escritura: 201 si se creó ahora, o 200 con `Idempotent-Replayed:
     * true` si fue un reintento que devolvió lo que ya existía (regla 7).
     *
     * @param  array<string, mixed>  $datos
     */
    protected static function escrito(array $datos, bool $repetido, int $creado = 201): JsonResponse
    {
        $respuesta = self::datos($datos, $repetido ? 200 : $creado);

        if ($repetido) {
            $respuesta->headers->set('Idempotent-Replayed', 'true');
        }

        return $respuesta;
    }
}
