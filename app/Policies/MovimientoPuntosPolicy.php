<?php

declare(strict_types=1);

namespace App\Policies;

use App\Compras\AnulacionCajero;
use App\Models\MovimientoPuntos;
use App\Models\Transaccion;
use App\Models\User;
use App\Panel\AlcancePanel;

/**
 * Who can record manual ledger operations. Accruals, expiries and promotions
 * are system operations, authorized by the features that call them.
 *
 * En el panel el libro es de solo lectura (regla dura 3.4, E10-06): nadie lo
 * edita ni lo borra, y cada administrador ve lo de su alcance.
 */
class MovimientoPuntosPolicy
{
    public function viewAny(User $user): bool
    {
        return AlcancePanel::esAdministrador($user);
    }

    public function view(User $user, MovimientoPuntos $movimiento): bool
    {
        return AlcancePanel::esAdministrador($user) && AlcancePanel::veDeSuComercio($user, $movimiento->comercio_id);
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

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
