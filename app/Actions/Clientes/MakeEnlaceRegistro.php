<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * El enlace que el cajero muestra como QR cuando no encuentra el documento
 * (E8-02, pantalla 1i; lo usa E4-02). Es una URL firmada y de vida corta que
 * abre el registro con el tipo y el número puestos. Nunca lleva datos de la
 * compra.
 */
class MakeEnlaceRegistro
{
    public function __invoke(TipoDocumento $tipo, string $documento): string
    {
        $minutos = config('registro.enlace_caja_minutes');

        return URL::temporarySignedRoute(
            'cliente.registro.caja',
            CarbonImmutable::now()->addMinutes(is_int($minutos) ? $minutos : 0),
            ['tipo' => $tipo->value, 'documento' => Cliente::normalizeDocumento($documento)],
        );
    }
}
