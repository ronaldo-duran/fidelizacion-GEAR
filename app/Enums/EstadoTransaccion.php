<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * State of a purchase. It only moves forward, once: pending to credited or
 * annulled, or credited to annulled.
 */
enum EstadoTransaccion: string implements HasLabel
{
    case Pendiente = 'pendiente';
    case Acreditada = 'acreditada';
    case Anulada = 'anulada';

    public function canMoveTo(self $next): bool
    {
        return match ($this) {
            self::Pendiente => $next === self::Acreditada || $next === self::Anulada,
            self::Acreditada => $next === self::Anulada,
            self::Anulada => false,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Acreditada => 'Acreditada',
            self::Anulada => 'Anulada',
        };
    }
}
