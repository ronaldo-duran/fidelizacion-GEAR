<?php

declare(strict_types=1);

namespace App\Actions\CredencialesApi;

use App\CredencialesApi\AlcanceCredencial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Revoca una credencial de la API (E9-01, regla 4): borra sus tokens y
 * desactiva al usuario de integración. El token deja de servir en la
 * siguiente petición. El usuario se conserva porque sus compras, reversas y
 * redenciones lo referencian.
 *
 * Bloquea la credencial como la rotación: si las dos chocan, una espera a la
 * otra y la credencial termina revocada y sin tokens.
 */
class RevokeCredencialApi
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $admin, int $credencialId): void
    {
        AlcanceCredencial::autorizarSobre($admin, User::query()->findOrFail($credencialId));

        DB::transaction(static function () use ($admin, $credencialId): void {
            $credencial = AlcanceCredencial::bloquear($credencialId);
            $borrados = $credencial->tokens()->getQuery()->toBase()->delete();
            $credencial->forceFill(['activo' => false])->save();

            AlcanceCredencial::auditar($admin, $credencial, 'credencial_revocada', ['tokens_borrados' => $borrados]);
        });
    }
}
