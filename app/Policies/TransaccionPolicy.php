<?php

declare(strict_types=1);

namespace App\Policies;

use App\Compras\AnulacionApi;
use App\Compras\AnulacionCajero;
use App\Enums\RolUsuario;
use App\Models\Transaccion;
use App\Models\User;
use App\Panel\AlcancePanel;

/**
 * Who registers and annuls purchases. A cashier registers only in their own
 * branch, a merchant administrator in any branch of their merchant and the
 * group administrator anywhere. A public API credential registers only in
 * its own branch (E9-01). Annulling follows the ledger reversal (E3-02); a
 * cashier annuls only their own purchases within the window (E4-02), and a
 * credential only the API purchases of its merchant.
 *
 * En el panel las compras son de solo lectura salvo la anulación (E10-06), y
 * cada administrador ve las de su alcance.
 */
class TransaccionPolicy
{
    public function viewAny(User $user): bool
    {
        return AlcancePanel::esAdministrador($user);
    }

    public function view(User $user, Transaccion $transaccion): bool
    {
        return AlcancePanel::esAdministrador($user) && AlcancePanel::veDeSuComercio($user, $transaccion->comercio_id);
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function register(User $user, int $comercioId, ?int $sedeId): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($user->esAdministradorDelGrupo()) {
            return true;
        }

        if ($user->comercio_id !== $comercioId) {
            return false;
        }

        return $user->esAdministradorDeComercio()
            || (in_array($user->rol, [RolUsuario::Cajero, RolUsuario::Integracion], true)
                && $sedeId !== null
                && $user->sede_id === $sedeId);
    }

    /**
     * Los administradores, el cajero solo para sus propias compras en plazo
     * (E4-02) y la credencial de la API solo para las compras de la API de su
     * comercio (E9-01).
     */
    public function annul(User $user, Transaccion $transaccion): bool
    {
        return $user->activo
            && ($user->esAdministradorDelGrupo()
                || ($user->esAdministradorDeComercio() && $user->comercio_id === $transaccion->comercio_id)
                || AnulacionCajero::permite($user, $transaccion)
                || AnulacionApi::permite($user, $transaccion));
    }
}
