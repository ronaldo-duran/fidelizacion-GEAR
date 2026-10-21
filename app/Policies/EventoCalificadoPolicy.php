<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TipoEventoCalificado;
use App\Models\EventoCalificado;
use App\Models\User;

/**
 * Who registers and closes qualifying events (spec E3-03): property
 * purchases only the group administrator; school enrollments also the
 * administrator of the school. Nobody edits or deletes them.
 */
class EventoCalificadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && ($user->esAdministradorDelGrupo() || $user->esAdministradorDeComercio());
    }

    public function view(User $user, EventoCalificado $evento): bool
    {
        return $this->viewAny($user) && $user->alcanzaComercio($evento->comercio_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function register(User $user, TipoEventoCalificado $tipo, int $comercioId): bool
    {
        if (! $user->activo) {
            return false;
        }

        return $user->esAdministradorDelGrupo()
            || ($tipo === TipoEventoCalificado::MatriculaColegio
                && $user->esAdministradorDeComercio()
                && $user->comercio_id === $comercioId);
    }

    public function close(User $user, EventoCalificado $evento): bool
    {
        return $this->register($user, $evento->tipo, $evento->comercio_id);
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
