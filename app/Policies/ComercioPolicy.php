<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comercio;
use App\Models\User;
use App\Panel\AlcancePanel;

/**
 * Los comercios en el panel (E2-02). El administrador del grupo crea y edita
 * todos. El de un comercio solo ve y edita el suyo, y no cambia la categoría,
 * el NIT ni si está activo: de la categoría sale la tasa de puntos, así que
 * esos campos son del grupo (el formulario los bloquea y `EditComercio` los
 * descarta). Nadie borra un comercio: se desactiva.
 */
class ComercioPolicy
{
    public function viewAny(User $user): bool
    {
        return AlcancePanel::esAdministrador($user);
    }

    public function view(User $user, Comercio $comercio): bool
    {
        return $this->viewAny($user) && $user->alcanzaComercio($comercio->id);
    }

    public function create(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
    }

    public function update(User $user, Comercio $comercio): bool
    {
        return $this->view($user, $comercio);
    }

    /**
     * La categoría, el NIT y el estado activo: solo el grupo.
     */
    public function updateDatosDelGrupo(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
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
