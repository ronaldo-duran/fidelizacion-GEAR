<?php

declare(strict_types=1);

namespace App\Reglas;

use App\Enums\TipoRegla;
use App\Models\Regla;
use Carbon\CarbonInterface;

/**
 * The accrual and bonus rule versions that were in force at an instant, ready
 * for the points calculator. A purchase is always calculated with the rules
 * of its own date (hard rule 3.3).
 */
class ReglasEnFecha
{
    /**
     * @return list<ReglaAplicada>
     */
    public function inForceAt(CarbonInterface $instant): array
    {
        $query = Regla::inForceAt($instant);
        $query->getQuery()
            ->whereIn('tipo', [TipoRegla::Acumulacion->value, TipoRegla::Bonificacion->value])
            ->orderBy('codigo');

        return array_values($query->get()
            ->map(static fn (Regla $regla): ReglaAplicada => ReglaAplicada::fromModel($regla))
            ->all());
    }
}
