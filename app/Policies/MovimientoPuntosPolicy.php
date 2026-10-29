<?php

declare(strict_types=1);

namespace App\Policies;

use App\Compras\AnulacionCajero;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use App\Models\User;

/**
 * Who can record manual ledger operations. Accruals, expiries and promotions
 * are system operations, authorized by the features that call them.
 */
class MovimientoPuntosPolicy
{
    public function recordAjusteManual(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
    }

    /**
     * The group administrator, or the administrator of the merchant where the
     * purchase happened. El cajero, solo al anular su propia compra en plazo (E4-02).
     */
    public function reverse(User $user, MovimientoPuntos $original): bool
    {
        if (! $user->activo) {
            return false;
        }

        return $user->esAdministradorDelGrupo()
            || ($user->esAdministradorDeComercio() && $user->comercio_id === $original->comercio_id)
            || $this->cajeroAnulaSuCompra($user, $original);
    }

    private function cajeroAnulaSuCompra(User $user, MovimientoPuntos $original): bool
    {
        $transaccion = $original->transaccion_id === null
            ? null
            : Transaccion::query()->find($original->transaccion_id);

        return $transaccion !== null && AnulacionCajero::permite($user, $transaccion);
    }
}
