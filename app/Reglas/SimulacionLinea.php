<?php

declare(strict_types=1);

namespace App\Reglas;

/**
 * One sample purchase of a simulation, with its points under the rules in
 * force and under the draft.
 */
final class SimulacionLinea
{
    public readonly CompraData $compra;

    public readonly PuntosDesglose $vigente;

    public readonly PuntosDesglose $borrador;

    public function __construct(CompraData $compra, PuntosDesglose $vigente, PuntosDesglose $borrador)
    {
        $this->compra = $compra;
        $this->vigente = $vigente;
        $this->borrador = $borrador;
    }

    public function diferencia(): int
    {
        return $this->borrador->puntos - $this->vigente->puntos;
    }
}
