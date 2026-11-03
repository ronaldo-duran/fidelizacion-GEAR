<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Solo el administrador del grupo define lo que cada comercio puede dar en
 * promociones (E3-06, regla 6).
 */
class ComercioPromocionLimitePolicy
{
    public function update(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
    }

    public function delete(): bool
    {
        return false;
    }
}
