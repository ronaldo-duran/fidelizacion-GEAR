<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Estado de un cumplimiento de misión (E3-06). Cambia una sola vez: de
 * pendiente a aprobado o rechazado.
 */
enum EstadoCumplimiento: string implements HasLabel
{
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }
}
