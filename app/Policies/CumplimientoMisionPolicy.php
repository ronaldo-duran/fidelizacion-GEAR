<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CumplimientoMision;
use App\Models\User;

/**
 * Revisa un cumplimiento un usuario activo del comercio dueño de la misión
 * con el permiso `aprueba_misiones` (E3-06, regla 8). No es un rol: puede ser
 * un cajero. El administrador del grupo no revisa cumplimientos.
 */
class CumplimientoMisionPolicy
{
    public function review(User $user, CumplimientoMision $cumplimiento): bool
    {
        return $user->activo
            && $user->aprueba_misiones
            && ! $user->esAdministradorDelGrupo()
            && $user->comercio_id === $cumplimiento->comercio_id;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
