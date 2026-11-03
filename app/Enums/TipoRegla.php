<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Rule types of the engine. Each one has its own sub-schema in
 * `resources/schemas/regla.json`.
 */
enum TipoRegla: string implements HasLabel
{
    case Acumulacion = 'acumulacion';
    case Niveles = 'niveles';
    case Bonificacion = 'bonificacion';
    case BonoRegistro = 'bono_registro';
    case Mision = 'mision';

    /**
     * Si la regla entra en el cálculo de puntos de una compra. El bono de
     * registro y las misiones dan puntos fijos por otros hechos (E3-06).
     */
    public function appliesToPurchases(): bool
    {
        return in_array($this, [self::Acumulacion, self::Bonificacion], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Acumulacion => 'Acumulación',
            self::Niveles => 'Niveles',
            self::Bonificacion => 'Bonificación',
            self::BonoRegistro => 'Bono de registro',
            self::Mision => 'Misión',
        };
    }
}
