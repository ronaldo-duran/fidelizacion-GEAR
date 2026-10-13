<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Access to the rule engine. Only the group administrator creates
 * rules and versions; nobody deletes them or edits them in place (hard rule
 * 3.2). Abilities receive the model as an extra argument and ignore it: the
 * permission does not depend on which rule it is.
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
     * "Updating" a rule means saving a new version, never editing it.
     */
    public function update(User $user): bool
    {
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
