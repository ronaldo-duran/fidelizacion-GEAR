<?php

declare(strict_types=1);

namespace App\Actions\Clientes;

use App\Enums\TipoDocumento;
use App\Models\Cliente;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Ingreso del cliente con tipo + número de documento y PIN (E8-02). Cada
 * intento se cuenta antes de comparar, con un incremento atómico, así varios
 * intentos simultáneos no pasan el tope; tras el máximo, ese documento queda
 * bloqueado el tiempo de la configuración, aunque llegue el PIN correcto. El
 * error nunca dice si el documento existe.
 */
class LoginCliente
{
    public const NO_COINCIDEN = 'El documento o el PIN no coinciden.';

    /**
     * @throws ValidationException
     */
    public function __invoke(
        TipoDocumento $tipo,
        string $documento,
        #[SensitiveParameter]
        string $pin,
    ): Cliente {
        $documento = Cliente::normalizeDocumento($documento);
        $minutos = self::setting('login_lockout_minutes');
        $key = "cliente-ingreso:{$tipo->value}|{$documento}";

        if (RateLimiter::hit($key, $minutos * 60) > self::setting('login_max_attempts')) {
            throw ValidationException::withMessages([
                'documento' => "Hiciste demasiados intentos. Espera {$minutos} minutos e intenta de nuevo.",
            ]);
        }

        $cliente = Cliente::query()
            ->where('tipo_documento', $tipo->value)
            ->where('documento', $documento)
            ->first();

        // Sin cliente o sin PIN también se compara contra un hash, para no revelar por el tiempo si existe.
        $valido = Hash::check($pin, $cliente->pin_hash ?? self::hashFicticio());

        if (! $valido || $cliente?->pin_hash === null) {
            throw ValidationException::withMessages(['documento' => self::NO_COINCIDEN]);
        }

        RateLimiter::clear($key);

        return $cliente;
    }

    private static function hashFicticio(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('sin-cliente');
    }

    private static function setting(string $key): int
    {
        $value = config("registro.{$key}");

        return is_int($value) ? $value : 0;
    }
}
