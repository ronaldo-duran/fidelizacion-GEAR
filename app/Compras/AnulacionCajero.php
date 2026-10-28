<?php

declare(strict_types=1);

namespace App\Compras;

use App\Enums\EstadoTransaccion;
use App\Enums\RolUsuario;
use App\Models\Transaccion;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Cuándo un cajero anula una compra desde la caja (E4-02): solo una que
 * registró él, en su sede, dentro del plazo de `config/cajero.php`. Fuera de
 * eso la anula un administrador. La anulación misma va por `AnnulCompra`.
 */
final class AnulacionCajero
{
    public static function permite(User $user, Transaccion $transaccion): bool
    {
        return $user->activo
            && $user->rol === RolUsuario::Cajero
            && $user->sede_id !== null
            && $transaccion->registrada_por === $user->id
            && $transaccion->sede_id === $user->sede_id
            && $transaccion->created_at->gte(self::desde());
    }

    /**
     * La última compra que el cajero aún puede anular, para "Anular la última
     * compra" en el inicio de la caja.
     */
    public static function ultima(User $user): ?Transaccion
    {
        $query = Transaccion::query()
            ->where('registrada_por', $user->id)
            ->where('sede_id', $user->sede_id)
            ->where('estado', '!=', EstadoTransaccion::Anulada->value)
            ->where('created_at', '>=', self::desde());
        $query->getQuery()->orderByDesc('id');

        return $query->first();
    }

    private static function desde(): CarbonImmutable
    {
        $horas = config('cajero.annul_hours');

        return CarbonImmutable::now()->subHours(is_int($horas) ? $horas : 0);
    }
}
