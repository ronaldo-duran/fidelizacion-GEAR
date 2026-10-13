<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages\Concerns;

use App\Models\User;

/**
 * `edita_reglas_json` is not mass assignable: only the user pages, which only
 * the group administrator reaches, set it explicitly.
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
        $user->save();

        return $user;
    }
}
