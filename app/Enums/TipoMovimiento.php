<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Points ledger movement types. There is no `redencion`: using a benefit never
 * costs points (hard rule 3.1).
 */
enum TipoMovimiento: string implements HasLabel
{
    case Acumulacion = 'acumulacion';
    case Caducidad = 'caducidad';
    case AjusteManual = 'ajuste_manual';
    case Reversa = 'reversa';
    case Promocion = 'promocion';

    public function getLabel(): string
    {
        return match ($this) {
            self::Acumulacion => 'Acumulación',
            self::Caducidad => 'Caducidad',
            self::AjusteManual => 'Ajuste manual',
            self::Reversa => 'Reversa',
            self::Promocion => 'Promoción',
        };
    }
}
