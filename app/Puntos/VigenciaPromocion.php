<?php

declare(strict_types=1);

namespace App\Puntos;

use App\Enums\TipoRegla;
use App\Models\Regla;
use Carbon\CarbonImmutable;

/**
 * Los puntos de una promoción (bono de registro o misión, E3-06) vencen como
 * los de una compra: a los meses de la regla de acumulación vigente (E3-05).
 */
class VigenciaPromocion
{
    /**
     * Null si no rige ninguna regla de acumulación.
     */
    public function hasta(CarbonImmutable $now): ?CarbonImmutable
    {
        $acumulacion = Regla::inForceAt($now)->where('tipo', TipoRegla::Acumulacion->value)->first();
        $meses = $acumulacion?->definicion['vigencia_puntos_meses'] ?? null;

        if (! is_int($meses)) {
            return null;
        }

        return $now->startOfDay()->addMonths($meses);
    }
}
