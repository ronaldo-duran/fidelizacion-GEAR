<?php

declare(strict_types=1);

namespace App\Actions\Verificacion;

use App\Enums\PropositoVerificacion;
use App\Models\CodigoVerificacion;
use App\Verificacion\CodigoHasher;
use Carbon\CarbonImmutable;

/**
 * Compara un código con el vigente de ese celular y propósito, en tiempo
 * constante. Un código sirve una vez y antes de vencer; tras el máximo de
 * intentos fallidos se invalida, aunque después llegue el correcto. Cada
 * decisión es un UPDATE condicionado a los intentos guardados, nunca a un
 * valor leído antes, para que verificaciones simultáneas no pasen el límite.
 * El registro (E8-02) la llama dentro de su propia transacción.
 */
class VerifyCode
{
    private readonly CodigoHasher $hasher;

    public function __construct(CodigoHasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public function __invoke(PropositoVerificacion $proposito, string $celular, string $codigo): bool
    {
        $celular = CodigoHasher::normalizeCelular($celular);

        if (! CodigoHasher::isCelular($celular)) {
            return false;
        }

        $now = CarbonImmutable::now();
        $activo = CodigoVerificacion::query()
            ->where('celular', $celular)
            ->where('proposito', $proposito->value)
            ->where('usado_at', null)
            ->where('invalidado_at', null)
            ->latest('id')
            ->first();

        if ($activo === null || $activo->vence_at->lt($now)) {
            return false;
        }

        if (hash_equals($activo->codigo_hash, $this->hasher->hash($proposito, $celular, trim($codigo)))) {
            return $this->markUsed($activo, $now);
        }

        $this->countFailure($activo, $now);

        return false;
    }

    /**
     * Se marca con un UPDATE condicionado a que el código siga sin usar y bajo
     * el límite de intentos: de dos verificaciones simultáneas solo una pasa, y
     * un código que llegó al límite nunca se acepta.
     */
    private function markUsed(CodigoVerificacion $codigo, CarbonImmutable $now): bool
    {
        $updated = CodigoVerificacion::query()->toBase()
            ->where('id', $codigo->id)
            ->whereNull('usado_at')
            ->whereNull('invalidado_at')
            ->where('intentos', '<', self::maxAttempts())
            ->update(['usado_at' => $now]);

        return $updated === 1;
    }

    /**
     * Incrementa los intentos de forma atómica y luego invalida el código si el
     * conteo guardado llegó al límite, sin importar lo que leyó esta petición.
     */
    private function countFailure(CodigoVerificacion $codigo, CarbonImmutable $now): void
    {
        CodigoVerificacion::query()->toBase()->where('id', $codigo->id)->increment('intentos');

        CodigoVerificacion::query()->toBase()
            ->where('id', $codigo->id)
            ->whereNull('invalidado_at')
            ->where('intentos', '>=', self::maxAttempts())
            ->update(['invalidado_at' => $now]);
    }

    private static function maxAttempts(): int
    {
        $max = config('verificacion.max_attempts');

        return is_int($max) ? $max : 0;
    }
}
