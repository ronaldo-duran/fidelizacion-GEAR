<?php

declare(strict_types=1);

namespace App\Actions\Verificacion;

use App\Enums\PropositoVerificacion;
use App\Models\CodigoVerificacion;
use App\Verificacion\CodigoHasher;
use Carbon\CarbonImmutable;

/**
 * Checks a code against the active one for that phone and purpose, in
 * constant time. A code works once and before it expires; after the maximum
 * wrong attempts it is invalidated, even if the right one arrives later.
 * Every decision is a conditional UPDATE on the stored attempts, never on a
 * value read before, so simultaneous verifications can't go past the limit.
 * Registration (E8-02) calls it inside its own transaction.
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
     * Marked with an UPDATE conditioned on the code still being unused and
     * under the attempt limit, so two simultaneous verifications accept only
     * one and a code that reached the limit is never accepted.
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
     * Increments the attempts atomically and then invalidates the code if the
     * stored count reached the limit, whatever this request read before.
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
