<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TipoRegla;
use App\Models\Regla;
use App\Models\User;

/**
 * Access to the rule engine. Only the group administrator creates
 * rules and versions; nobody deletes them or edits them in place (hard rule
 * 3.2). The exception is a mission (E3-06, decision 2): the administrator of
 * a merchant also creates and versions the missions of that merchant.
 */
class ReglaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->managesReglas($user);
    }

    public function view(User $user): bool
    {
        return $this->managesReglas($user);
    }

    public function create(User $user): bool
    {
        return $this->managesReglas($user);
    }

    /**
     * Una misión del comercio: el grupo para cualquiera, el administrador de
     * comercio solo para el suyo.
     */
    public function createMision(User $user, int $comercioId): bool
    {
        return $this->managesReglas($user)
            || ($user->activo && $user->esAdministradorDeComercio() && $user->comercio_id === $comercioId);
    }

    /**
     * "Updating" a rule means saving a new version, never editing it.
     */
    public function update(User $user, ?Regla $regla = null): bool
    {
        $comercioId = $regla?->definicion['comercio_id'] ?? null;

        if ($regla?->tipo === TipoRegla::Mision && is_int($comercioId)) {
            return $this->createMision($user, $comercioId);
        }

        return $this->managesReglas($user);
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }

    public function restore(): bool
    {
        return false;
    }

    public function forceDelete(): bool
    {
        return false;
    }

    /**
     * The advanced JSON editor sits behind a separate permission.
     */
    public function editJson(User $user): bool
    {
        return $this->managesReglas($user) && $user->edita_reglas_json;
    }

    private function managesReglas(User $user): bool
    {
        return $user->activo && $user->esAdministradorDelGrupo();
    }
}
