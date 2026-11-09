<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Cliente;

/**
 * Lo que el POS necesita para atender a un cliente (E9-01, regla 11): el
 * primer nombre y el nivel efectivo. Nunca el celular, el correo, la fecha de
 * nacimiento, el saldo ni los movimientos (Ley 1581).
 */
final class ClienteJson
{
    /**
     * @return array<string, mixed>
     */
    public static function datos(Cliente $cliente): array
    {

        return [
            'tipo_documento' => $cliente->tipo_documento->value,
            'documento' => $cliente->documento,
            'primer_nombre' => self::primerNombre($cliente->nombre_completo),
            'nivel_efectivo' => $cliente->nivel_efectivo->value,
            'nivel_efectivo_desde' => $cliente->nivel_efectivo_desde?->toIso8601String(),
        ];
    }

    public static function primerNombre(?string $nombreCompleto): ?string
    {
        $palabras = preg_split('/\s+/u', trim((string) $nombreCompleto), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($palabras) && $palabras !== [] ? $palabras[0] : null;
    }
}
