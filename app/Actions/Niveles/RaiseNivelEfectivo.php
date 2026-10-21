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
 * Synchronous promotion (hard rule 3.6): recomputes the three sources and
 * leaves the effective level at the higher of the current one and the
 * computed one. It never lowers it, so any request may call it: the purchase
 * registration (E4-01) inside its own transaction, the qualifying events and
 * the memberships (E6-01).
 */
class RaiseNivelEfectivo
{
    private readonly NivelEfectivo $niveles;

    public function __construct(NivelEfectivo $niveles)
    {
        $this->niveles = $niveles;
    }

    public function __invoke(int $clienteId, MotivoCambioNivel $motivo): Cliente
    {
        if (! $motivo->isAscenso()) {
            throw new LogicException('A promotion needs a promotion reason.');
        }

        return DB::transaction(function () use ($clienteId, $motivo): Cliente {
            $cliente = $this->niveles->lockCliente($clienteId);
            $calculado = $this->niveles->refreshFuentes(
                $cliente,
                $this->niveles->definicionNiveles(),
                CarbonImmutable::today(),
            );

            if ($calculado->isAbove($cliente->nivel_efectivo)) {
                $this->niveles->change($cliente, $calculado, $motivo);
            }

            if (! $cliente->nivel_efectivo->isAbove($calculado)) {
                $cliente->forceFill(['proteccion_hasta' => null]);
            }

            $cliente->save();

            return $cliente;
        });
    }
}
