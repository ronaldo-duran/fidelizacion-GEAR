<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Por qué un correo está en la lista de supresión (E7-01; la llena E7-02).
 * Rebote y queja bloquean todo correo; la baja de mercadeo, solo el de
 * mercadeo.
 */
enum MotivoSupresion: string implements HasLabel
{
    case Rebote = 'rebote';
    case Queja = 'queja';
    case BajaMercadeo = 'baja_mercadeo';

    public function bloquea(EventoNotificacion $evento): bool
    {
        return $this !== self::BajaMercadeo || $evento->esMercadeo();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Rebote => 'Rebote',
            self::Queja => 'Queja',
            self::BajaMercadeo => 'Baja de mercadeo',
        };
    }
}
