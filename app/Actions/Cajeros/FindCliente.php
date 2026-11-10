<?php

declare(strict_types=1);

namespace App\Actions\Cajeros;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Busca al cliente por tipo y número de documento desde la caja (E4-02) o
 * desde la API (E9-01). La respuesta dice si alguien es cliente del programa,
 * así que cada búsqueda cuenta contra un tope por usuario y por minuto: una
 * cuenta de caja, una sesión robada o una credencial no pueden recorrer
 * documentos (Ley 1581).
 */
class FindCliente
{
    public const DEMASIADAS = 'Hiciste demasiadas búsquedas. Espera un minuto e intenta de nuevo.';

    /**
     * @throws ValidationException en la caja, al pasar el tope
     * @throws ThrottleRequestsException en la API, al pasar el tope (429 con Retry-After, regla 12)
     */
    public function __invoke(User $cajero, TipoDocumento $tipo, string $documento): ?Cliente
    {
        $api = $cajero->esIntegracion();
        $tope = config($api ? 'api.customer_lookups_per_minute' : 'cajero.search_max_per_minute');
        $llave = "cajero-busqueda:{$cajero->id}";

        if (RateLimiter::hit($llave, 60) > (is_int($tope) ? $tope : 0)) {
            throw $api
                ? new ThrottleRequestsException(self::DEMASIADAS, null, [
                    'Retry-After' => RateLimiter::availableIn($llave),
                ])
                : ValidationException::withMessages(['form.documento' => self::DEMASIADAS]);
        }

        return Cliente::query()
            ->where('tipo_documento', $tipo->value)
            ->where('documento', Cliente::normalizeDocumento($documento))
            ->first();
    }
}
