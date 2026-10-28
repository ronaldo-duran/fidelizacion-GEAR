<?php

declare(strict_types=1);

namespace App\Actions\Cajeros;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Ingreso del cajero a la caja (E4-02) con la cuenta del sistema: correo y
 * contraseña. Solo entran usuarios activos con rol cajero y sede asignada. El
 * error es el mismo para una cuenta que no existe, una contraseña errada o
 * una cuenta que no puede entrar, así no revela si la cuenta existe. Cada
 * intento se cuenta antes de comparar; tras el máximo, ese correo queda
 * bloqueado el tiempo de la configuración.
 */
class LoginCajero
{
    public const NO_COINCIDEN = 'El correo o la contraseña no coinciden.';

    /**
     * @throws ValidationException
     */
    public function __invoke(
        string $email,
        #[SensitiveParameter]
        string $password,
    ): User {
        $email = mb_strtolower(trim($email));
        $minutos = self::setting('login_lockout_minutes');
        $key = "cajero-ingreso:{$email}";

        if (RateLimiter::hit($key, $minutos * 60) > self::setting('login_max_attempts')) {
            throw ValidationException::withMessages([
                'email' => "Hiciste demasiados intentos. Espera {$minutos} minutos e intenta de nuevo.",
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        // Sin usuario también se compara contra un hash, para no revelar por el tiempo si existe.
        $valido = Hash::check($password, $user->password ?? self::hashFicticio());

        if (! $valido || $user === null || ! $user->puedeEntrarALaCaja()) {
            throw ValidationException::withMessages(['email' => self::NO_COINCIDEN]);
        }

        RateLimiter::clear($key);

        return $user;
    }

    private static function hashFicticio(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('sin-cajero');
    }

    private static function setting(string $key): int
    {
        $value = config("cajero.{$key}");

        return is_int($value) ? $value : 0;
    }
}
