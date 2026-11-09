<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Beneficios\ChequeoBeneficio;
use App\Enums\ResultadoBeneficio;

/**
 * El resultado de validar un código de beneficio sin gastarlo (E9-01,
 * operación 6), como las pantallas del cajero (E5-02): `valido`, `ya_usado`
 * o `no_valido`, con el motivo. Los datos del cliente solo van si el código
 * es válido.
 */
final class ChequeoCodigoJson
{
    /**
     * @return array<string, mixed>
     */
    public static function datos(ChequeoBeneficio $chequeo): array
    {
        $beneficio = $chequeo->beneficio;
        $uso = $chequeo->ultimoUso;

        return [
            'resultado' => match ($chequeo->resultado) {
                ResultadoBeneficio::Valido => 'valido',
                ResultadoBeneficio::Usado => 'ya_usado',
                default => 'no_valido',
            },
            'motivo' => $chequeo->resultado->value,
            'mensaje' => $chequeo->resultado->mensaje(),
            'beneficio' => $beneficio === null ? null : [
                'beneficio_id' => $beneficio->id,
                'nombre' => $beneficio->nombre,
                'tipo' => $beneficio->tipo->value,
                'requiere_soporte' => $beneficio->requiere_soporte,
                'valor' => $chequeo->estado?->valor,
            ],
            'cliente' => $chequeo->cliente === null ? null : [
                'primer_nombre' => ClienteJson::primerNombre($chequeo->cliente->nombre_completo),
                'nivel_efectivo' => $chequeo->cliente->nivel_efectivo->value,
            ],
            'ultimo_uso' => $uso === null ? null : [
                'usado_at' => $uso->created_at->toIso8601String(),
                'sede_id' => $uso->sede_id,
            ],
            'vuelve' => $chequeo->vuelve?->toDateString(),
        ];
    }
}
