<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MovimientoPuntos;
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
     * purchase happened.
     */
    public function reverse(User $user, MovimientoPuntos $original): bool
    {
        if (! $user->activo) {
            return false;
        }

        return $user->esAdministradorDelGrupo()
            || ($user->esAdministradorDeComercio() && $user->comercio_id === $original->comercio_id);
    }
}
