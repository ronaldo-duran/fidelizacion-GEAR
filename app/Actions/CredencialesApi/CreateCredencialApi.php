<?php

declare(strict_types=1);

namespace App\Actions\CredencialesApi;

use App\CredencialesApi\AlcanceCredencial;
use App\CredencialesApi\CredencialApiEmitida;
use App\Enums\AlcanceApi;
use App\Enums\RolUsuario;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea una credencial de la API pública (E9-01, regla 4 y decisión 2): un
 * usuario de integración de una sede, con su token y sus alcances. El token
 * se devuelve una sola vez; la base guarda solo su huella SHA-256.
 *
 * El usuario no entra al panel ni a la caja: su correo es interno y su
 * contraseña aleatoria nunca se muestra.
 */
class CreateCredencialApi
{
    /**
     * @param  list<AlcanceApi>  $alcances
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $admin, string $nombre, int $sedeId, array $alcances): CredencialApiEmitida
    {
        $sede = Sede::query()->find($sedeId);

        AlcanceCredencial::autorizar($admin, $sede?->comercio_id);

        $nombre = trim($nombre);
        $errores = array_filter([
            'nombre' => $nombre === '' || mb_strlen($nombre) > 100
                ? 'Escribe un nombre de hasta 100 caracteres.'
                : null,
            'sede_id' => $sede === null ? 'Elige la sede de la credencial.' : null,
            'alcances' => $alcances === [] ? 'Elige al menos un permiso.' : null,
        ]);

        if ($errores !== [] || $sede === null) {
            throw ValidationException::withMessages($errores);
        }

        return DB::transaction(static function () use ($nombre, $sede, $alcances): CredencialApiEmitida {
            $credencial = User::query()->forceCreate([
                'name' => $nombre,
                'email' => 'api-' . Str::uuid()->toString() . '@credenciales.invalid',
                'password' => Str::random(64),
                'rol' => RolUsuario::Integracion,
                'comercio_id' => $sede->comercio_id,
                'sede_id' => $sede->id,
                'activo' => true,
            ]);

            return AlcanceCredencial::emitir($credencial, $alcances);
        });
    }
}
