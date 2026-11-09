<?php

declare(strict_types=1);

namespace App\Actions\Cajeros;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Busca al cliente por tipo y número de documento desde la caja (E4-02). La
 * respuesta dice si alguien es cliente del programa, así que cada búsqueda
 * cuenta contra un tope por cajero y por minuto: una cuenta de caja, o una
 * sesión robada, no puede recorrer documentos (Ley 1581).
 */
class FindCliente
{
    public const DEMASIADAS = 'Hiciste demasiadas búsquedas. Espera un minuto e intenta de nuevo.';

    /**
     * @throws ValidationException
     */
    public function __invoke(User $cajero, TipoDocumento $tipo, string $documento): ?Cliente
    {
        // La credencial de la API tiene su propio tope de consultas (E9-01, regla 12).
        $tope = config($cajero->esIntegracion() ? 'api.customer_lookups_per_minute' : 'cajero.search_max_per_minute');

        if (RateLimiter::hit("cajero-busqueda:{$cajero->id}", 60) > (is_int($tope) ? $tope : 0)) {
            throw ValidationException::withMessages(['form.documento' => self::DEMASIADAS]);
        }

        return Cliente::query()
            ->where('tipo_documento', $tipo->value)
            ->where('documento', Cliente::normalizeDocumento($documento))
            ->first();
    }
}
