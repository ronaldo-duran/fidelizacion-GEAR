<?php

declare(strict_types=1);

namespace App\Actions\Membresias;

use App\Actions\Niveles\RaiseNivelEfectivo;
use App\Enums\MotivoCambioNivel;
use App\Membresias\PeriodoCalculator;
use App\Models\Membresia;
use App\Models\PagoMembresia;
use App\Niveles\NivelEfectivo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Otorga el periodo de un pago aprobado (E6-01, regla 7), en una sola
 * transacción: crea el periodo, deja en el cliente el nivel del plan y el fin
 * más lejano de sus periodos, y lo asciende al momento (regla dura 3.6).
 * No toca `saldo_puntos` ni el libro (regla dura 3.1).
 *
 * El `pago_id` único de `membresias` impide un segundo periodo para el mismo
 * pago aunque esta acción se llame dos veces.
 */
class GrantMembresia
{
    private readonly NivelEfectivo $niveles;

    private readonly PeriodoCalculator $periodos;

    private readonly RaiseNivelEfectivo $raise;

    public function __construct(NivelEfectivo $niveles, PeriodoCalculator $periodos, RaiseNivelEfectivo $raise)
    {
        $this->niveles = $niveles;
        $this->periodos = $periodos;
        $this->raise = $raise;
    }

    public function __invoke(PagoMembresia $pago): Membresia
    {
        return DB::transaction(function () use ($pago): Membresia {
            $cliente = $this->niveles->lockCliente($pago->cliente_id);
            [$desde, $hasta] = $this->periodos->periodo(
                CarbonImmutable::today(),
                $cliente->membresia_vigente_hasta,
                $pago->duracion_meses,
            );

            $membresia = Membresia::query()->create([
                'cliente_id' => $cliente->id,
                'plan_id' => $pago->plan_id,
                'pago_id' => $pago->id,
                'nivel' => $pago->nivel,
                'vigente_desde' => $desde,
                'vigente_hasta' => $hasta,
            ]);

            $cliente->forceFill(['nivel_por_membresia' => $pago->nivel, 'membresia_vigente_hasta' => $hasta])->save();
            ($this->raise)($cliente->id, MotivoCambioNivel::AscensoMembresia);

            return $membresia;
        });
    }
}
