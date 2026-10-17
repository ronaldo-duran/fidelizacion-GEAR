<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\Nivel;
use Carbon\CarbonImmutable;

/**
 * The three level sources of a customer on a given day, each one only if it
 * is valid that day (hard rule 3.5).
 */
final class FuentesNivel
{
    public readonly Nivel $porPuntos;

    public readonly ?Nivel $porMembresia;

    public readonly ?Nivel $porEvento;

    public readonly ?CarbonImmutable $eventoVigenteHasta;

    public function __construct(
        Nivel $porPuntos,
        ?Nivel $porMembresia,
        ?Nivel $porEvento,
        ?CarbonImmutable $eventoVigenteHasta,
    ) {
        $this->porPuntos = $porPuntos;
        $this->porMembresia = $porMembresia;
        $this->porEvento = $porEvento;
        $this->eventoVigenteHasta = $eventoVigenteHasta;
    }

    /**
     * The level these sources give: the highest valid one.
     */
    public function nivel(): Nivel
    {
        return Nivel::highest($this->porPuntos, $this->porMembresia, $this->porEvento);
    }
}
