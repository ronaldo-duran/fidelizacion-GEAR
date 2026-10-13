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

    public function getLabel(): string
    {
        return match ($this) {
            self::Acumulacion => 'Acumulación',
            self::Niveles => 'Niveles',
            self::Bonificacion => 'Bonificación',
        };
    }
}
