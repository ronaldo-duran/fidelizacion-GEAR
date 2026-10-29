<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cómo se expresa el valor de un beneficio (E5-01): un porcentaje entero de 0
 * a 100 o un monto fijo en pesos enteros (regla dura 3.9). No hay producto
 * gratis (§8: exige inventario).
 */
enum TipoBeneficio: string implements HasLabel
{
    case Porcentaje = 'porcentaje';
    case MontoFijo = 'monto_fijo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Porcentaje => 'Porcentaje de descuento',
            self::MontoFijo => 'Monto fijo en pesos',
        };
    }
}
