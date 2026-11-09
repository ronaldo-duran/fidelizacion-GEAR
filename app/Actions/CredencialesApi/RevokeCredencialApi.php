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
 */
class RevokeCredencialApi
{
    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $admin, int $credencialId): void
    {
        $credencial = User::query()->findOrFail($credencialId);

        AlcanceCredencial::autorizarSobre($admin, $credencial);

        DB::transaction(static function () use ($admin, $credencial): void {
            $borrados = $credencial->tokens()->getQuery()->toBase()->delete();
            $credencial->forceFill(['activo' => false])->save();

            AlcanceCredencial::auditar($admin, $credencial, 'credencial_revocada', ['tokens_borrados' => $borrados]);
        });
    }
}
