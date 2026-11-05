<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Por dónde le llega un aviso al cliente (E7-01). Sin WhatsApp ni SMS
 * (CLAUDE.md §8).
 */
enum CanalNotificacion: string implements HasLabel
{
    case Correo = 'correo';
    case App = 'app';

    public function getLabel(): string
    {
        return match ($this) {
            self::Correo => 'Correo',
            self::App => 'En la app',
        };
    }
}
