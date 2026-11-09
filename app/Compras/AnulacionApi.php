<?php

declare(strict_types=1);

namespace App\Compras;

use App\Enums\CanalCompra;
use App\Models\Transaccion;
use App\Models\User;

/**
 * Cuándo una credencial de la API anula una compra (E9-01, decisión 4): solo
 * una registrada por la API en su mismo comercio. El plazo es el de la
 * reversa del libro (E3-02), que lo revisa la anulación misma. Las compras
 * de la caja se anulan en la caja o en el panel.
 */
final class AnulacionApi
{
    public static function permite(User $user, Transaccion $transaccion): bool
    {
        return $user->activo
            && $user->esIntegracion()
            && $user->comercio_id === $transaccion->comercio_id
            && $transaccion->canal === CanalCompra::Api;
    }
}
