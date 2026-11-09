<?php

declare(strict_types=1);

namespace App\CredencialesApi;

use App\Enums\AlcanceApi;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Lo común a crear, rotar y revocar una credencial de la API (E9-01, regla
 * 4): quién puede hacerlo, cómo se emite el token y la constancia en la
 * auditoría de lo que el observador de usuarios no ve.
 */
final class AlcanceCredencial
{
    /**
     * El administrador del grupo, en cualquier comercio; el administrador de
     * comercio, solo en el suyo.
     *
     * @throws AuthorizationException
     */
    public static function autorizar(User $admin, ?int $comercioId): void
    {
        $deSuComercio = $admin->esAdministradorDeComercio()
            && $comercioId !== null
            && $admin->comercio_id === $comercioId;
        $puede = $admin->activo && ($admin->esAdministradorDelGrupo() || $deSuComercio);

        if (! $puede) {
            throw new AuthorizationException('No puedes gestionar credenciales de este comercio.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    public static function autorizarSobre(User $admin, User $credencial): void
    {
        if (! $credencial->esIntegracion()) {
            throw new AuthorizationException('Ese usuario no es una credencial de la API.');
        }

        self::autorizar($admin, $credencial->comercio_id);
    }

    /**
     * @param  list<AlcanceApi>  $alcances
     */
    public static function emitir(User $credencial, array $alcances): CredencialApiEmitida
    {
        $valores = array_values(array_unique(array_map(
            static fn (AlcanceApi $alcance): string => $alcance->value,
            $alcances,
        )));
        $token = $credencial->createToken($credencial->name, $valores);

        return new CredencialApiEmitida($credencial, $token->plainTextToken);
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    public static function auditar(User $admin, User $credencial, string $evento, array $detalle): void
    {
        Auditoria::query()->create([
            'usuario_id' => $admin->id,
            'evento' => $evento,
            'modelo' => User::class,
            'modelo_id' => $credencial->id,
            'antes' => null,
            'despues' => $detalle,
            'created_at' => now(),
        ]);
    }
}
