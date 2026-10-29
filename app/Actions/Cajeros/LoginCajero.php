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
 * bloqueado desde esa IP el tiempo de la configuración. La llave lleva la IP
 * para que nadie deje sin ingresar a un cajero ajeno escribiendo su correo.
 *
 * Además se cuentan los intentos fallidos por IP, como en el ingreso del
 * cliente, para que probar muchos correos desde un mismo origen se frene.
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
        ?string $ip = null,
    ): User {
        $email = mb_strtolower(trim($email));
        $minutos = self::setting('login_lockout_minutes');
        $key = "cajero-ingreso:{$email}|{$ip}";
        $keyIp = $ip === null ? null : "cajero-ingreso-ip:{$ip}";
        $bloqueado = ValidationException::withMessages([
            'email' => "Hiciste demasiados intentos. Espera {$minutos} minutos e intenta de nuevo.",
        ]);

        if ($keyIp !== null && RateLimiter::tooManyAttempts($keyIp, self::setting('login_max_attempts_per_ip'))) {
            throw $bloqueado;
        }

        if (RateLimiter::hit($key, $minutos * 60) > self::setting('login_max_attempts')) {
            throw $bloqueado;
        }

        $user = User::query()->where('email', $email)->first();

        // Sin usuario también se compara contra un hash, para no revelar por el tiempo si existe.
        $valido = Hash::check($password, $user->password ?? self::hashFicticio());

        if (! $valido || $user === null || ! $user->puedeEntrarALaCaja()) {
            if ($keyIp !== null) {
                RateLimiter::hit($keyIp, $minutos * 60);
            }

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
