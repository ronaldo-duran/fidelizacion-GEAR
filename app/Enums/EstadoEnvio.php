<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Estado de un envío de notificación (E7-01). Un correo pasa de pendiente
 * a enviado o fallido; un aviso en la app nace enviado. Omitido lleva su
 * motivo.
 */
enum EstadoEnvio: string implements HasLabel
{
    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Omitido = 'omitido';
    case Fallido = 'fallido';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Enviado => 'Enviado',
            self::Omitido => 'Omitido',
            self::Fallido => 'Fallido',
        };
    }
}
