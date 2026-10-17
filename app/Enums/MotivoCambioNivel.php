<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why an effective level changed. Only the nightly evaluation lowers a level
 * (hard rule 3.6).
 */
enum MotivoCambioNivel: string implements HasLabel
{
    case AscensoCompra = 'ascenso_compra';
    case AscensoEvento = 'ascenso_evento';
    case AscensoMembresia = 'ascenso_membresia';
    case DescensoNocturno = 'descenso_nocturno';

    public function isAscenso(): bool
    {
        return $this !== self::DescensoNocturno;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AscensoCompra => 'Ascenso por compra',
            self::AscensoEvento => 'Ascenso por evento',
            self::AscensoMembresia => 'Ascenso por membresía',
            self::DescensoNocturno => 'Descenso en la evaluación nocturna',
        };
    }
}
