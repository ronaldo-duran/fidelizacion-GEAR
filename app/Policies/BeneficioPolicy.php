<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Beneficio;
use App\Models\User;

/**
 * Quién administra beneficios (E5-01): el administrador del grupo, todos; el
 * de un comercio, solo los de su comercio. El cajero no administra
 * beneficios. Un beneficio con redenciones no se borra: se desactiva.
 */
class BeneficioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ($user->esAdministradorDelGrupo() || $user->esAdministradorDeComercio());
    }

    public function view(User $user, Beneficio $beneficio): bool
    {
        return $this->viewAny($user) && $user->alcanzaComercio($beneficio->comercio_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Guardar un beneficio de ese comercio, nuevo o editado.
     */
    public function save(User $user, int $comercioId): bool
    {
        return $this->viewAny($user) && $user->alcanzaComercio($comercioId);
    }

    public function update(User $user, Beneficio $beneficio): bool
    {
        return $this->save($user, $beneficio->comercio_id);
    }

    public function delete(User $user, Beneficio $beneficio): bool
    {
        return $this->update($user, $beneficio) && ! $beneficio->tieneRedenciones();
    }

    public function deleteAny(): bool
    {
        return false;
    }
}
