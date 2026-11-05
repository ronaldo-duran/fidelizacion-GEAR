<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Las plantillas de los avisos (E7-01) las edita solo el administrador del
 * grupo. Los eventos los define el código: no se crean ni se borran.
 */
class PlantillaNotificacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
