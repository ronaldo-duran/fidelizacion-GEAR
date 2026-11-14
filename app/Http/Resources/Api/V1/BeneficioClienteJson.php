<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Beneficios\BeneficioParaCliente;

/**
 * Un beneficio del comercio con su estado para el cliente (E9-01, operación
 * 2), calculado por E5-01. El valor es el del nivel del cliente; un
 * bloqueado dice qué nivel lo abre, sin los puntos que faltan, que delatarían
 * el saldo.
 */
final class BeneficioClienteJson
{
    /**
     * @return array<string, mixed>
     */
    public static function datos(BeneficioParaCliente $item): array
    {
        $beneficio = $item->beneficio;

        return [
            'beneficio_id' => $beneficio->id,
            'nombre' => $beneficio->nombre,
            'tipo' => $beneficio->tipo->value,
            'condiciones_texto' => $beneficio->condiciones_texto,
            'requiere_soporte' => $beneficio->requiere_soporte,
            'estado' => $item->estado->value,
            'valor' => $item->valor,
            'nivel_requerido' => $item->nivelRequerido?->value,
            'vuelve' => $item->vuelve?->toDateString(),
            'proxima_fecha' => $item->proximaFecha?->toDateString(),
        ];
    }
}
