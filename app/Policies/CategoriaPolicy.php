<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Las categorías (E2-02): solo el administrador del grupo. De su slug dependen
 * las tasas del motor de reglas, así que no se borran.
 */
class CategoriaPolicy
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
