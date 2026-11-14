<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Redencion;

/**
 * Una redención entregada por la API (E9-01, operación 7), con el valor
 * otorgado en pesos enteros (regla dura 3.11).
 */
final class RedencionJson
{
    /**
     * @return array<string, mixed>
     */
    public static function datos(Redencion $redencion): array
    {
        return [
            'referencia' => $redencion->referencia,
            'beneficio_id' => $redencion->beneficio_id,
            'sede_id' => $redencion->sede_id,
            'valor_otorgado_cop' => $redencion->valor_otorgado_cop,
            'monto_cuenta_cop' => $redencion->monto_cuenta_cop,
            'soporte' => $redencion->soporte,
            'created_at' => $redencion->created_at->toIso8601String(),
        ];
    }
}
