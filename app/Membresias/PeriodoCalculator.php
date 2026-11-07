<?php

declare(strict_types=1);

namespace App\Membresias;

use Carbon\CarbonImmutable;

/**
 * Fechas de un periodo de membresía (E6-01, regla 6). Pura.
 *
 * Empieza el día en que se aprueba el pago, o el día siguiente al fin de la
 * membresía vigente si el cliente renueva antes de que venza. Termina el día
 * anterior a la misma fecha N meses después, sin desbordar al mes siguiente.
 */
final class PeriodoCalculator
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} desde y hasta, inclusive
     */
    public function periodo(CarbonImmutable $aprobadoEl, ?CarbonImmutable $vigenteHastaActual, int $meses): array
    {
        $desde = $aprobadoEl->startOfDay();

        if ($vigenteHastaActual !== null && $vigenteHastaActual->startOfDay()->gte($desde)) {
            $desde = $vigenteHastaActual->startOfDay()->addDay();
        }

        return [$desde, $desde->addMonthsNoOverflow($meses)->subDay()];
    }
}
