<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Los usuarios del sistema (E2-01): solo el administrador del grupo los crea y
 * edita. Un usuario no se borra: se desactiva, para conservar quién registró
 * cada compra.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }
}
