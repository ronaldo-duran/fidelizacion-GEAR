<?php

declare(strict_types=1);

namespace App\Puntos;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * El plazo para revertir los puntos de una compra (E3-02): los días de
 * `puntos.reversal_window_days` desde el día en que se acreditaron. La misma
 * cuenta la usan la reversa y el panel, que oculta "Anular" fuera de plazo.
 */
final class PlazoReversa
{
    public static function dias(): int
    {
        $days = config('puntos.reversal_window_days');

        return is_int($days) ? $days : 0;
    }

    public static function vencido(CarbonInterface $acreditadaAt): bool
    {
        $purchaseDay = CarbonImmutable::instance($acreditadaAt)->startOfDay();

        return CarbonImmutable::now()->startOfDay()->gt($purchaseDay->addDays(self::dias()));
    }
}
