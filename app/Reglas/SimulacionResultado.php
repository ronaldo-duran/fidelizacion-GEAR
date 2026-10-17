<?php

declare(strict_types=1);

namespace App\Reglas;

/**
 * Outcome of a rule simulation: one line per sample purchase, in order.
 */
final class SimulacionResultado
{
    /** @var list<SimulacionLinea> */
    public readonly array $lineas;

    /**
     * @param  list<SimulacionLinea>  $lineas
     */
    public function __construct(array $lineas)
    {
        $this->lineas = $lineas;
    }

    public function totalVigente(): int
    {
        return array_sum(array_map(static fn (SimulacionLinea $linea): int => $linea->vigente->puntos, $this->lineas));
    }

    public function totalBorrador(): int
    {
        return array_sum(array_map(static fn (SimulacionLinea $linea): int => $linea->borrador->puntos, $this->lineas));
    }
}
