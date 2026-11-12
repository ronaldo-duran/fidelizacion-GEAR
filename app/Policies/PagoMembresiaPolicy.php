<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Los pagos de membresía (E6-01) en el panel: solo lectura y solo para el
 * administrador del grupo. Un pago solo cambia por la notificación firmada de
 * la pasarela.
 */
class PagoMembresiaPolicy
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

    public function deleteAny(): bool
    {
        return false;
    }
}
