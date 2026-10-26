<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Enums\RolUsuario;
use App\Models\User;

/**
 * `edita_reglas_json` is not mass assignable: only the user pages, which only
 * the group administrator reaches, set it explicitly.
 *
 * El comercio y la sede ocultos para el rol no llegan en el formulario, así
 * que se limpian aquí: al pasar un cajero a administrador del grupo no le
 * queda un comercio viejo.
 */
trait AssignsReglaJsonPermission
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveUser(User $user, array $data): User
    {
        $user->fill($data);
        $user->edita_reglas_json = ($data['edita_reglas_json'] ?? false) === true;

        if ($user->rol !== RolUsuario::Cajero) {
            $user->sede_id = null;
        }

        if ($user->rol === RolUsuario::AdministradorGrupo) {
            $user->comercio_id = null;
        }

        $user->save();

        return $user;
    }
}
