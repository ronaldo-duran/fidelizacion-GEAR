<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Los planes de membresía (E6-01): solo el administrador del grupo. Un plan no
 * se borra: se desactiva, porque los pagos y los periodos lo referencian.
 */
class PlanMembresiaPolicy
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
