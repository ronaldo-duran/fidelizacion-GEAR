<?php

declare(strict_types=1);

namespace App\Jobs\Nocturno;

use Carbon\CarbonImmutable;

/**
 * The nightly processes for one cutoff date, in the order they must run.
 */
final class ProcesosNocturnos
{
    /**
     * @return list<NightlyJob>
     */
    public static function for(CarbonImmutable $corte): array
    {
        $fecha = $corte->toDateString();

        return [
            new ExpirePuntos($fecha),
            new ReevaluateNiveles($fecha),
            new RegisterAvisosDescenso($fecha),
            new VerifySaldos($fecha),
            new PurgeCredenciales($fecha),
            new PurgeCodigosVerificacion($fecha),
            new PurgeRegistrosPendientes($fecha),
        ];
    }
}
