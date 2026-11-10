<?php

declare(strict_types=1);

namespace App\Actions\CredencialesApi;

use App\CredencialesApi\AlcanceCredencial;
use App\CredencialesApi\CredencialApiEmitida;
use App\Enums\AlcanceApi;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Rota el token de una credencial de la API (E9-01, regla 4): emite uno nuevo
 * con los mismos alcances y le pone al vigente un vencimiento de unas horas
 * (`api.rotation_grace_hours`), para que el POS cambie de token sin cortar
 * ventas. Pasada esa ventana solo sirve el nuevo.
 *
 * La credencial se bloquea dentro de la transacción y ahí se revisa que siga
 * activa: una rotación que choca con una revocación nunca deja un token vivo
 * en una credencial revocada, y dos rotaciones a la vez no dejan dos tokens
 * sin vencimiento.
 */
class RotateCredencialApi
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $admin, int $credencialId): CredencialApiEmitida
    {
        AlcanceCredencial::autorizarSobre($admin, User::query()->findOrFail($credencialId));

        return DB::transaction(static function () use ($admin, $credencialId): CredencialApiEmitida {
            $credencial = AlcanceCredencial::bloquear($credencialId);

            if (! $credencial->activo) {
                throw ValidationException::withMessages(['credencial' => 'Esta credencial está revocada.']);
            }

            $tokens = $credencial->tokens()->getQuery();
            $tokens->getQuery()->whereNull('expires_at')->orderBy('id');
            $vigentes = $tokens->get();
            $alcances = self::alcances($vigentes->last());

            if ($alcances === []) {
                throw ValidationException::withMessages([
                    'credencial' => 'Esta credencial no tiene un token vigente para rotar.',
                ]);
            }

            $horas = config('api.rotation_grace_hours');
            $vence = CarbonImmutable::now()->addHours(is_int($horas) ? $horas : 0);

            foreach ($vigentes as $token) {
                $token->forceFill(['expires_at' => $vence])->save();
            }

            AlcanceCredencial::auditar($admin, $credencial, 'credencial_rotada', [
                'token_anterior_vence_at' => $vence->toIso8601String(),
            ]);

            return AlcanceCredencial::emitir($credencial, $alcances);
        });
    }

    /**
     * Los alcances del token vigente: el nuevo nace con los mismos.
     *
     * @return list<AlcanceApi>
     */
    private static function alcances(?PersonalAccessToken $token): array
    {
        $valores = $token === null ? [] : (array) $token->abilities;

        return array_values(array_filter(array_map(
            static fn (mixed $valor): ?AlcanceApi => is_string($valor) ? AlcanceApi::tryFrom($valor) : null,
            $valores,
        )));
    }
}
