<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\TipoMovimiento;
use App\Models\Cliente;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;

/**
 * Una compra como la ve el POS (E9-01, regla 9): estado, puntos otorgados y
 * el nivel efectivo del cliente en este momento. Nunca el saldo (regla dura
 * 3.1). Los puntos se leen de las acumulaciones de la compra, así un
 * reintento responde lo mismo que la primera vez (decisión 5).
 */
final class CompraJson
{
    /**
     * @return array<string, mixed>
     */
    public static function datos(Transaccion $compra): array
    {
        $puntos = MovimientoPuntos::query()->toBase()
            ->where('transaccion_id', $compra->id)
            ->where('tipo', TipoMovimiento::Acumulacion->value)
            ->sum('puntos');
        $nivel = $compra->cliente_id === null
            ? null
            : Cliente::query()->whereKey($compra->cliente_id)->toBase()->value('nivel_efectivo');

        return [
            'referencia_externa' => $compra->referencia_externa,
            'estado' => $compra->estado->value,
            'tipo_documento' => $compra->tipo_documento_cliente->value,
            'documento' => $compra->documento_cliente,
            'monto' => $compra->monto,
            'sede_id' => $compra->sede_id,
            'puntos_otorgados' => (int) $puntos,
            'nivel_efectivo' => is_string($nivel) ? $nivel : null,
            'registrada_at' => $compra->created_at->toIso8601String(),
        ];
    }
}
