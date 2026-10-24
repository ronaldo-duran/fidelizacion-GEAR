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
     * Marked with an UPDATE conditioned on the code still being unused, so
     * two simultaneous verifications accept only one.
     */
    private function markUsed(CodigoVerificacion $codigo, CarbonImmutable $now): bool
    {
        $updated = CodigoVerificacion::query()->toBase()
            ->where('id', $codigo->id)
            ->whereNull('usado_at')
            ->whereNull('invalidado_at')
            ->update(['usado_at' => $now]);

        return $updated === 1;
    }

    private function countFailure(CodigoVerificacion $codigo, CarbonImmutable $now): void
    {
        CodigoVerificacion::query()->toBase()->where('id', $codigo->id)->increment('intentos');

        $max = config('verificacion.max_attempts');

        if ($codigo->intentos + 1 >= (is_int($max) ? $max : 0)) {
            CodigoVerificacion::query()->toBase()
                ->where('id', $codigo->id)
                ->whereNull('invalidado_at')
                ->update(['invalidado_at' => $now]);
        }
    }
}
