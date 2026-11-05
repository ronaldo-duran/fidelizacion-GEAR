<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Estado de un pago de membresía (E6-01). Solo un pago pendiente cambia:
 * aprobado, rechazado y anulado son finales.
 */
enum EstadoPago: string implements HasLabel
{
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Anulado = 'anulado';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
