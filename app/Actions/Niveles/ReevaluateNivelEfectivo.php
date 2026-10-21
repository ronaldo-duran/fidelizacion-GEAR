<?php

declare(strict_types=1);

namespace App\Actions\Niveles;

use App\Enums\MotivoCambioNivel;
use App\Models\Cliente;
use App\Niveles\NivelEfectivo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Nightly evaluation: the only place where a level goes down (hard rule
 * 3.6). It refuses to run outside the console (queue worker or scheduler)
 * and an architecture test keeps it out of anything but `App\Console` and
 * `App\Jobs`.
 *
 * When the valid sources give less than the current level, the customer is
 * protected for the `proteccion_meses` of the `niveles` rule (today 0, so the
 * drop happens that same night) and lowered once the protection is over. It
 * never raises: promotions go through RaiseNivelEfectivo.
 */
class ReevaluateNivelEfectivo
{
    private readonly NivelEfectivo $niveles;

    public function __construct(NivelEfectivo $niveles)
    {
        $this->niveles = $niveles;
    }

    public function __invoke(int $clienteId): Cliente
    {
        if (! app()->runningInConsole()) {
            throw new LogicException('Levels are only lowered by the nightly evaluation.');
        }

        return DB::transaction(function () use ($clienteId): Cliente {
            $cliente = $this->niveles->lockCliente($clienteId);
            $today = CarbonImmutable::today();
            $definicion = $this->niveles->definicionNiveles();
            $calculado = $this->niveles->refreshFuentes($cliente, $definicion, $today);

            if (! $cliente->nivel_efectivo->isAbove($calculado)) {
                $cliente->forceFill(['proteccion_hasta' => null])->save();

                return $cliente;
            }

            $proteccionHasta = $cliente->proteccion_hasta ?? $today->addMonths($this->proteccionMeses($definicion));

            if ($today->lt($proteccionHasta)) {
                $cliente->forceFill(['proteccion_hasta' => $proteccionHasta])->save();

                return $cliente;
            }

            $this->niveles->change($cliente, $calculado, MotivoCambioNivel::DescensoNocturno);
            $cliente->forceFill(['proteccion_hasta' => null])->save();

            return $cliente;
        });
    }

    /**
     * @param  array<array-key, mixed>  $definicion
     */
    private function proteccionMeses(array $definicion): int
    {
        $meses = $definicion['proteccion_meses'] ?? 0;

        return is_int($meses) && $meses > 0 ? $meses : 0;
    }
}
