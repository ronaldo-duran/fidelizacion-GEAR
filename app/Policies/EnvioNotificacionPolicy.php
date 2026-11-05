<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * El registro de envíos (E7-01) es de solo lectura, para soporte, y solo lo
 * ve el administrador del grupo.
 */
class EnvioNotificacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(): bool
    {
        return false;
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
