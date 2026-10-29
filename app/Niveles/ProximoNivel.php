<?php

declare(strict_types=1);

namespace App\Niveles;

use App\Enums\Nivel;
use App\Models\Cliente;

/**
 * Cuántos puntos le faltan al cliente para el nivel siguiente, con la regla
 * `niveles` vigente ("Le faltan 255 para Oro", pantalla 1g).
 */
class ProximoNivel
{
    private readonly NivelEfectivo $niveles;

    private readonly NivelCalculator $calculator;

    public function __construct(NivelEfectivo $niveles, NivelCalculator $calculator)
    {
        $this->niveles = $niveles;
        $this->calculator = $calculator;
    }

    /**
     * @return array{nivel: Nivel, faltan: int}|null null en Diamante
     */
    public function para(Cliente $cliente): ?array
    {
        return $this->calculator->siguiente(
            $cliente->nivel_efectivo,
            $cliente->saldo_puntos,
            $this->niveles->definicionNiveles(),
        );
    }
}
